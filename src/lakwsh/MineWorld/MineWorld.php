<?php
/**
 * 本插件于2018年5月6日开源
 * 禁止一切倒卖
 * 转载请注明出处,谢谢
 * 作者: 数字
 * QQ: 1181334648
 * 如非必要将不再更新此插件
 * 此插件的售后服务于2018年5月7日起终止
 */
namespace lakwsh\MineWorld;
use pocketmine\Player;
use pocketmine\block\Lava;
use pocketmine\block\Water;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\scheduler\CallbackTask;

class MineWorld extends PluginBase implements Listener{
	//	插件初始化类
	//	变量定义
	private $cfg;
	private $inArea=array();
	//	插件开启
	public function onEnable(){
		if(!defined('MWCP')) define('MWCP',$this->getDataFolder());
		if(!is_dir(MWCP)) mkdir(MWCP,0777,true);
		date_default_timezone_set('Asia/Hong_Kong');
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
		self::getSetting();
		self::updateMOTD();
		if($this->cfg['显示状态栏']){
			$tick=$this->cfg['状态栏更新频率'];
			if($tick<1){
				$this->getLogger()->warning('错误的状态栏更新频率!使用默认值:5次/秒');
				$tick=4;
			}
			$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,'ShowInfo']),$tick);
		}
		$this->getLogger()->notice('插件初始化成功!感谢您购买本插件!如有问题请到售后群咨询,谢谢!');
	}
	//	自定义函数
	//	状态栏
	public function ShowInfo(){
		$cfg=$this->cfg;
		$server=$this->getServer();
		/**api**/
		$mny=self::getPlugin('EconomyAPI');
		$apl=self::getPlugin('AreaManage');
		$elp=self::getPlugin('EasyAuth');
		/**api**/
		$barloc='';
		for($i=0;$i<$cfg['状态栏右偏'];$i++) $barloc.=' ';
		$players=$server->getOnlinePlayers();
		$online=count($players);
		foreach($players as $player){
			$world=$player->getLevel()->getFolderName();
			$x=round($player->x);
			$y=round($player->y);
			$z=round($player->z);
			$item=$player->getItemInHand();
			$id=$item->getID();
			$da=$item->getDamage();
			$ina=$item->getName();
			$hp=$player->getHealth();
			$mhp=$player->getMaxHealth();
			$tps=$server->getTicksPerSecond();
			$load=$server->getTickUsage();
			$msg=$barloc.join("\n".$barloc,$cfg['状态栏显示内容'])."\n";
			for($i=0;$i<$cfg['状态栏上移'];$i++) $msg.="\n";
			$msg=str_ireplace(array('&online&','&world&','&x&','&y&','&z&','&id&','&da&','&ina&','&hp&','&time&','&name&','&tps&','&load&'),array($online,$world,$x,$y,$z,$id,$da,$ina,$hp.'/'.$mhp,date('m-d H:i:s'),$player->getName(),$tps,$load),$msg);
			/**api**/
			if($apl!=null){
				$area=$apl->isProtectArea(array('object'=>$player));
				if($area!==false){$msg=str_ireplace('&isProtect&',$area,$msg);}else{$msg=str_ireplace('&isProtect&','否',$msg);}
			}
			else $msg=str_ireplace('&isProtect&','未知',$msg);
			if($elp!=null){
				if($elp->isLogin($player)){
					$msg=str_ireplace('&isLogin&','已登录',$msg);
				}else{
					$tok=$elp->GetTimeOut($player);
					if($tok==null) $msg=str_ireplace('&isLogin&','未登陆',$msg);
					else $msg=str_ireplace('&isLogin&','请在'.$tok.'秒内登陆',$msg);
				}
			}
			else $msg=str_ireplace('&isLogin&','未知',$msg);
			if($mny!=null) $msg=str_ireplace('&myMoney&',$mny->myMoney($player),$msg);
			else $msg=str_ireplace('&myMoney&','未知',$msg);
			/**api**/
			$player->sendTip($msg);
		}
	}
	//	更新MOTD
	private function updateMOTD($i=0){
		$server=$this->getServer();
		$server->getNetwork()->setName(str_replace(array('{NOW}','{MAX}'),array(count($server->getOnlinePlayers())+$i,$server->getMaxPlayers()),$this->cfg['MOTD']));
		return;
	}
	//	倒计时关闭服务器
	public function ShutdownTimer(){
		$server=$this->getServer();
		$time=$this->shutdownTimer;
		if($time>0){
			if($time<=10 or $time%5==0) $server->broadcastMessage(TextFormat::RED.'倒计时'.$time.'秒后关闭服务器');
			$this->shutdownTimer--;
		}else{$server->shutdown();}
		return;
	}
	//	确保所有名称相同
	private function SgetName($object){
		return strtolower(trim($object->getName()));
	}
	//	钩子函数
	//	玩家执行命令
	public function onCommand(CommandSender $sender,Command $cmd,$label,array $args){
		$cfg=$this->cfg;
		$server=$this->getServer();
		if(!$sender->isOp()){
			$sender->sendMessage(TextFormat::RED.'你无权使用此命令');
			return true;
		}
		if($cmd=='stopserver'){
			if(!isset($args[0])) return false;
			if(!is_numeric($args[0])) return false;
			$server->broadcastMessage(TextFormat::RED.'服务器即将进行例行维护,将在倒计时'.$args[0].'秒后关闭服务器');
			$this->shutdownTimer=$args[0]-1;
			$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,'ShutdownTimer']),20);
			return true;
		}elseif($cmd=='disable'){
			if($sender instanceof Player){
				$sender->sendMessage(TextFormat::RED.'此命令只能在控制台使用');
				return true;
			}
			if(!isset($args[0])) return false;
			$plugin=self::getPlugin($args[0]);
			if($plugin!=null){
				$server->getPluginManager()->disablePlugin($plugin);
				$this->getLogger()->info('插件 '.$args[0].' 已关闭.');
			}else{
				$this->getLogger()->warning('插件不存在.');
			}
			return true;
		}elseif($cmd=='bmsg'){
            if(count($args)<4) return false;
            if(!isset($args[4])) $args[4]='';
            foreach($server->getOnlinePlayers() as $p) $p->sendActionBar($args[3],$args[4],(int)$args[0]*20,(int)$args[1]*20,(int)$args[2]*20);
		    return true;
        }
		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED.'此命令只能在游戏中使用');
			return true;
		}
		if($cmd=='banitem'){
			$item=$sender->getItemInHand();
			$id=$item->getId();
			if($id>0){
				if(in_array($id,$cfg['禁用物品'])){
					foreach($cfg['禁用物品'] as $key=>$itemid){
						if($itemid==$id){
							unset($cfg['禁用物品'][$key]);
							$sender->sendMessage(TextFormat::GREEN.'已取消禁止此物品');
							break;
						}
					}
				}else{
					$cfg['禁用物品'][]=$id;
					$sender->sendMessage(TextFormat::GREEN.'已禁止此物品');
				}
				self::saveConfigFile($cfg);
				return true;
			}
			$sender->sendMessage(TextFormat::RED.'请手持需要禁止/解禁的物品再执行此命令');
			return true;
		}elseif($cmd=='god'){
			if($cfg['OP无敌模式']){
				$cfg['OP无敌模式']=false;
				$sender->sendMessage(TextFormat::RED.'已关闭');
			}else{
				$cfg['OP无敌模式']=true;
				$sender->sendMessage(TextFormat::GREEN.'已开启');
			}
			self::saveConfigFile($cfg);
			return true;
		}
		return true;
	}
	//	玩家移动
	public function PlayerMoveEoveEvent(PlayerMoveEvent $event){
		$cfg=$this->cfg;
		$player=$event->getPlayer();
		$name=self::SgetName($player);
		if(round($player->y)<-15 and $cfg['防止掉出世界']){
			$player->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
			$this->getServer()->broadcastMessage(TextFormat::YELLOW.$name.'掉出了世界,已被拉回复活点');
			return;
		}
		$api=self::getPlugin('AreaManage');
		if($api!=null){
			if(!isset($this->inArea[$name])) $this->inArea[$name]=false;
			if($api->isProtectArea(array('object'=>$player))!==false){
				if(!$this->inArea[$name]){
					$player->sendMessage(TextFormat::YELLOW.'你已进入保护区域');
					$this->inArea[$name]=true;
				}
			}else{
				if($this->inArea[$name]){
					$player->sendMessage(TextFormat::YELLOW.'你已离开保护区域');
					$this->inArea[$name]=false;
				}
			}
		}
	}
	//	玩家破坏方块
	public function onBlockBreak(BlockBreakEvent $event){
		if($event->isCancelled()) return;
		if($event->getBlock()->getY()<1 and $this->cfg['禁止破坏最底层']) $event->setCancelled(true);
		return;
	}
	//	玩家手持物品
	public function onPlayerInteract(PlayerInteractEvent $event){
		if($event->isCancelled()) return;
		if(in_array($event->getItem()->getId(),$this->cfg['禁用物品'])){
			$event->getPlayer()->sendMessage(TextFormat::RED.'此物品已被禁用.');
			$event->setCancelled(true);
			return;
		}
	}
	//	玩家放置方块
	public function onItemConsume(PlayerItemConsumeEvent $event){
		if($event->isCancelled()) return;
		if(in_array($event->getItem()->getId(),$this->cfg['禁用物品'])){
			$event->getPlayer()->sendMessage(TextFormat::RED.'此物品已被禁用.');
			$event->setCancelled(true);
			return;
		}
	}
	//	玩家离开游戏
	public function onPlayerQuit(PlayerQuitEvent $event) {
		self::updateMOTD(-1);
	}
	//	玩家传送
	public function EntityTeleport(EntityTeleportEvent $event){
		if($event->isCancelled()) return;
		$e=$event->getEntity();
		$to=$event->getTo();
		if($e instanceof Player) $this->getLogger()->info(self::SgetName($e).'已传送到'.self::SgetName($to->getLevel()).'(x:'.$to->getX().',y:'.$to->getY().',z:'.$to->getZ().')');
	}
	//	实体被伤害
	public function onEntityDamage(EntityDamageEvent $event){
		if($event->isCancelled()) return;
		$cfg=$this->cfg;
		$ent=$event->getEntity();
		if($event instanceof EntityDamageByEntityEvent){
			$damager=$event->getDamager();
			if($damager instanceof Player){
				if($damager->isOp() and $cfg['OP秒杀模式']){
					$ent->kill();
					return;
				}
				if($cfg['创造模式禁止PVP']){
					if($damager->getGamemode()!=0 and $ent instanceof Player){
						$event->setCancelled(true);
						return;
					}
				}
			}
		}
		if($ent instanceof Player){
			if($cfg['出生点保护']){
				if($ent->getPosition()->distance($this->getServer()->getDefaultLevel()->getSpawnLocation())<=$cfg['出生点保护半径']){
					$event->setCancelled(true);
					return;
				}
			}
			if($ent->isOp() and $cfg['OP无敌模式']){
				$event->setCancelled(true);
				return;
			}
		}
	}
	//	实体爆炸
	public function onExplosion(ExplosionPrimeEvent $event){
		if($event->isCancelled()) return;
		if($this->cfg['禁止爆炸']) $event->setCancelled(true);
		return;
	}
	//	方块更新
	public function onBlockUpdate(BlockUpdateEvent $event){
		if($event->isCancelled()) return;
		$cfg=$this->cfg;
		$block=$event->getBlock();
		if($block instanceof Water){
			if($cfg['禁止水流动']) $event->setCancelled(true);
		}elseif($block instanceof Lava){
			if($cfg['禁止岩浆流动']) $event->setCancelled(true);
		}
		return;
	}
	public function onItemSpawn(ItemSpawnEvent $event){
		if($this->cfg['显示掉落物品名称']){
			$item=$event->getEntity();
			$item->setNameTag($item->getItem()->getName());
			$item->setNameTagAlwaysVisible(true);
		}
		return;
	}
	//	插件全局参数类
	//	插件配置
	private function getSetting(){
		$data=array(
			'显示状态栏'=>true,
			'状态栏显示内容'=>array('§a玩家名: &name&','§6登录状态: &isLogin&','§a在线: &online&','§c血量: &hp&','§c坐标: &x&,&y&,&z&','§4保护区域: &isProtect&','§9世界: &world&','§d手持: &ina&<&id&:&da&>','§9金钱: &myMoney&','§3时间: &time&','§6Tps: &tps&','§cLoad: &load&'),
			'状态栏右偏'=>50,
			'状态栏上移'=>13,
			'OP无敌模式'=>false,
			'OP秒杀模式'=>false,
			'禁止破坏最底层'=>true,
			'禁止爆炸'=>true,
			'禁止水流动'=>true,
			'禁止岩浆流动'=>true,
			'防止掉出世界'=>true,
			'出生点保护'=>true,
			'出生点保护半径'=>10,
			'MOTD'=>'§bMCPE Server [{NOW}/{MAX}]',
			'状态栏更新频率'=>4,
			'创造模式禁止PVP'=>true,
			'禁用物品'=>array(),
			'显示掉落物品名称'=>true
		);
		$getdata=self::getConfigFile();
		if($getdata==null){
			self::saveConfigFile($data,false);
			$this->cfg=$data;
		}else{
			$checkdata=self::checkConfig($data,$getdata);
			if($checkdata!=$getdata) self::saveConfigFile($checkdata,false);
			$this->cfg=$checkdata;
		}
		return;
	}
	//	配置文件类
	//	配置文件->读取
	private function getConfigFile(){
		$path=MWCP.'config.yml';
		if(!file_exists($path)){
			return null;
		}else{
			$config=new Config($path,Config::YAML);
			return $config->getAll();
		}
	}
	//	配置文件->写入
	private function saveConfigFile(array $config,$reset=true){
		$data=new Config(MWCP.'config.yml',Config::YAML);
		$data->setAll($config);
		$data->save();
		if($reset) self::getSetting();
		return;
	}
	//	配置文件格式检测
	private function checkConfig($ori,$check){
		foreach(array_keys($ori) as $key){
			if(isset($check[$key])){
				if(is_bool($ori[$key])){
					if(is_bool($check[$key])) $ori[$key]=$check[$key];
				}elseif(is_int($ori[$key])){
					if(is_int($check[$key])) $ori[$key]=abs($check[$key]);
				}elseif(is_array($ori[$key])){
					if(is_array($check[$key])) $ori[$key]=$check[$key];
				}else{$ori[$key]=$check[$key];}
			}
		}
		if($ori!==$check) $this->getLogger()->emergency('配置文件中部分配置项错误!已恢复为默认值,请注意.');
		return $ori;
	}
	//	接口类
	//	插件调用
	private function getPlugin($name){
		$man=$this->getServer()->getPluginManager();
		$plu=$man->getPlugin($name);
		if($plu!=null) if($man->isPluginEnabled($plu)){return $plu;}
		else return null;
	}
}