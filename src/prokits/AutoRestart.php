<?php

//we know that this code is foolish :(

namespace prokits;

use LogicException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use prokits\events\PreRestartEvent;
use RuntimeException;
use SplFileObject;

final class AutoRestart extends PluginBase {
	private static $instance;
	/** @var \pocketmine\utils\Config */
	private $settings;
	private $loadTime;
	private $unixShell = null;
	/** @var Task */
	private $task;
	
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
		
		if(Utils::getOS() !== Utils::OS_UNKNOWN && Utils::getOS() !== Utils::OS_WINDOWS) {
			$execute = trim(shell_exec('which bash') ?? shell_exec('which zsh') ?? shell_exec('which sh') ?? shell_exec('which ksh') ?? shell_exec('which csh') ?? shell_exec('which tcsh') ?? shell_exec('which dash'));
			if(!empty($execute)) {
				$this->getLogger()->alert('We Will use ' . $execute . ' to run scripts');
				$this->unixShell = $execute;
			}
		}
		
		$this->task = new class extends Task {
			
			public $tick = 0;
			
			public function onRun(int $currentTick) {
				$autoRestartTime = AutoRestart::getInstance()->getSettings()->get('autoRestartTime');
				$delay = $autoRestartTime - $this->tick;
				if($delay < 16) {
					Server::getInstance()->broadcastMessage('§r[§bAutoRestart§r] >> Server will restart in §e' . $delay . ' §rSecond');
				}
				if($autoRestartTime === $this->tick) {
					AutoRestart::getInstance()->restart(0);
				}
				$this->tick++;
			}
		};
		
		$this->getScheduler()->scheduleRepeatingTask($this->task , 20);
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
		if($sender->isOp()) {
			if(mb_strtolower($label) === 'restart') {
				if(isset($args[0])) {
					switch(mb_strtolower($args[0])) {
						case '-t':
						case '--time':
							if(!isset($args[1])) {
								$sender->sendMessage(TextFormat::RED . 'Syntax Error in Args.');
								return true;
							}
							if($args[1] === 'n' || $args[1] === 'now') {
								$this->restart(0);
								return true;
							}
							$this->restart((int) $args[1]);
							break;
						case '-st':
						case '--setTime':
							if(!isset($args[1])) {
								$sender->sendMessage(TextFormat::RED . 'Syntax Error in Args.');
								return true;
							}
							if($args[1] < 1) {
								$sender->sendMessage(TextFormat::RED . 'Invalid Time.');
								return true;
							}
							$this->settings->set('autoRestartTime' , (int) $args[1]);
							$this->task->tick = 0;
							break;
						case '-p':
						case '--path':
							if(!isset($args[1])) {
								$sender->sendMessage(TextFormat::RED . 'Syntax Error in Args.');
								return true;
							}
							$pathContainer = new TextContainer([
								'SP' => $this->getServer()->getDataPath() ,
							]);
							$path = $pathContainer->getText($args[1]);
							try {
								$file = new SplFileObject($path);
							} catch(RuntimeException $runtimeException) {
								$sender->sendMessage(TextFormat::RED . "File $path cannot open.");
								return true;
							} catch(LogicException $logicException) {
								$sender->sendMessage(TextFormat::RED . "$path is a directory.");
								return true;
							}
							if(isset($file)) {
								$this->settings->set('file' , $path);
								$sender->sendMessage(TextFormat::GREEN . "Set Script path to $path");
							}
							break;
						case '-c':
						case '--cancel':
							$sender->sendMessage(TextFormat::GOLD . 'TODO');
							break;
					}
				} else {
					$sender->sendMessage(TextFormat::RED . 'Syntax Error in Args.');
				}
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
		$shell = $this->unixShell;
		register_shutdown_function(function() use ($setting , $shell) {
			if($setting->exists('file') && file_exists($setting->get('file'))) {
				if($shell !== null) {
					pcntl_exec($shell , [$setting->get('file')]);
					return;
				}
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