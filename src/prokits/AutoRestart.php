<?php


namespace prokits;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use prokits\events\PreRestartEvent;
use SplFileObject;

final class AutoRestart extends PluginBase {
	private static $instance;
	/** @var \pocketmine\utils\Config */
	private $settings;
	private $loadTime;
	
	public static function getInstance() : ?self {
		return self::$instance;
	}
	
	public function onEnable() {
		$this->loadTime = time();
		self::$instance = $this;
		$this->getLogger()->info('AutoRestart Plugin Loaded');
		$this->settings = new Config($this->getDataFolder() . '/config.yml' , Config::YAML , [
			'autoRestartTime' => 60 ,
		]);
		$this->getScheduler()->scheduleRepeatingTask(new class extends Task {
			protected $tick = 0;
			
			public function onRun(int $currentTick) {
				if($this->tick - AutoRestart::getInstance()->getSettings()->get('autoRestartTime') <= 10) {
					Server::getInstance()->broadcastMessage('§r[§bAutoRestart§r] >> Server will restart in §e' . $this->tick - AutoRestart::getInstance()->getSettings()->get('autoRestartTime') . ' §rMinutes');
				}
				if(AutoRestart::getInstance()->getSettings()->get('autoRestartTime') === $this->tick) {
					AutoRestart::getInstance()->restart(0);
				}
				$this->tick++;
			}
		} , 20 * 60);
	}
	
	public function onDisable() {
		$this->getLogger()->info('AutoRestart Plugin Loaded');
		$this->settings->save();
	}
	
	
	public function restart(int $delay) {
		$event = new PreRestartEvent();
		$this->getServer()->getPluginManager()->callEvent($event);
		if(!$event->isCancelled()) {
			$this->actuallyrestart($delay);
		}
	}
	
	public function onCommand(CommandSender $sender , Command $command , string $label , array $args) : bool {
		if(mb_strtolower($label) === 'restart') {
			if(isset($args[0])) {
				switch(mb_strtolower($args[0])) {
					case 'n':
					case 'now':
						$this->restart(0);
						break;
					case 't':
					case 'time':
						if(!isset($args[1])) {
							$sender->sendMessage(TextFormat::RED . 'Syntax Error in Args.');
							return true;
						}
						$this->restart((int) $args[1]);
						break;
					case 'p':
					case 'path':
						if(!isset($args[1])) {
							$sender->sendMessage(TextFormat::RED . 'Syntax Error in Args.');
							return true;
						}
						$pathContainer = new TextContainer([
							'SERVER_PATH' => $this->getServer()->getDataPath() ,
						]);
						$path = $pathContainer->getText($args[1]);
						
						if(!file_exists($args[1])) {
							$sender->sendMessage(TextFormat::RED . "File not found $path.");
							return true;
						}
						
						$file = new SplFileObject($path);
						if(!$file->isReadable()) {
							$sender->sendMessage(TextFormat::RED . "Cannot Read File $path.");
							return true;
						}
						
						$this->settings->set('file' , $path);
						$sender->sendMessage(TextFormat::GREEN . "Set Script path to $path");
						break;
					case 'c':
					case 'cancel':
						$sender->sendMessage(TextFormat::GOLD . 'TODO');
						break;
				}
			} else {
				$sender->sendMessage(TextFormat::RED . 'Syntax Error in Args.');
			}
		}
		return true;
	}
	
	public function getSettings() : Config {
		return $this->settings;
	}
	
	public function getLoadTime() : int {
		return $this->loadTime;
	}
	
	private function actuallyRestart(int $delay) {
		$setting = $this->getSettings();
		register_shutdown_function(function() use ($setting) {
			if($setting->exists('file')) {
				pcntl_exec($setting->get('file'));
			}
		});
		$this->getScheduler()->scheduleDelayedTask(new class extends Task {
			public function onRun(int $currentTick) {
				Server::getInstance()->shutdown();
			}
		} , $delay);
	}
}