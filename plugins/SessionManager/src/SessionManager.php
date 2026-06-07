<?php

declare(strict_types=1);

namespace alvin0319\SessionManager;

use Generator;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use SOFe\AwaitGenerator\Await;
use function count;

/**
 * @phpstan-type SessionCreateCallback = \Closure(string, ?Player=, bool=) : Generator<mixed, mixed, mixed, SessionHandle<BaseSession>|null>
 */
final class SessionManager extends PluginBase{
	use SingletonTrait;

	/** @phpstan-var \WeakMap<Plugin, SessionCreateCallback> */
	private static \WeakMap $sessionCreateCallbacks;

	/** @phpstan-var array<string, list<BaseSession>> */
	private array $sessions = [];

	/** @phpstan-var \WeakMap<BaseSession, Plugin> */
	private \WeakMap $sessionToPlugin;

	protected function onLoad() : void{
		self::setInstance($this);
		self::$sessionCreateCallbacks = new \WeakMap();
		$this->sessionToPlugin = new \WeakMap();
	}

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvent(PlayerLoginEvent::class, $this->onPlayerLogin(...), EventPriority::LOWEST, $this);
		$this->getServer()->getPluginManager()->registerEvent(PlayerQuitEvent::class, $this->onPlayerQuit(...), EventPriority::NORMAL, $this);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			$this->tickSessions();
		}), 20);
	}

	private function tickSessions() : void{
		$xuidsToRemove = [];
		$sessionsToSave = [];

		foreach($this->sessions as $xuid => $sessions){
			$remainingSessions = [];

			foreach($sessions as $session){
				$session->internalTick();

				if($session->getOfflineSeconds() >= BaseSession::MAX_OFFLINE_SECONDS){
					$sessionsToSave[] = $session;
				}else{
					$remainingSessions[] = $session;
				}
			}

			if(count($remainingSessions) === 0){
				$xuidsToRemove[] = $xuid;
			}else{
				$this->sessions[$xuid] = $remainingSessions;
			}
		}

		foreach($xuidsToRemove as $xuid){
			unset($this->sessions[$xuid]);
		}

		foreach($sessionsToSave as $session){
			Await::f2c(function() use ($session) : Generator{
				try{
					yield from $session->save(true);
					$plugin = $this->sessionToPlugin[$session] ?? null;
					if($plugin instanceof SessionProvider){
						yield from $plugin->removeSession($session->getXuid());
					}
					unset($this->sessionToPlugin[$session]);
				}catch(\Throwable $e){
					$this->getLogger()->logException($e);
				}
			});
		}

		if(count($sessionsToSave) > 0){
			$this->getLogger()->debug("Cleaned up " . count($sessionsToSave) . " sessions.");
		}
	}

	public function onDisable() : void{
		$this->sessions = [];
	}

	public static function registerSessionCreate(Plugin $plugin) : void{
		if(!$plugin instanceof SessionProvider){
			throw new \InvalidArgumentException("Plugin must implement SessionProvider to register session create callback");
		}
		if(!isset(self::$sessionCreateCallbacks)){
			self::$sessionCreateCallbacks = new \WeakMap();
		}
		self::$sessionCreateCallbacks[$plugin] = $plugin->createSession(...);
	}

	private function onPlayerLogin(PlayerLoginEvent $event) : void{
		$player = $event->getPlayer();
		$xuid = $player->getXuid();

		if(isset($this->sessions[$xuid])){
			$validSessions = [];
			foreach($this->sessions[$xuid] as $session){
				try{
					$session->switchOnline($player);
					$validSessions[] = $session;
				}catch(\Throwable $e){
					$this->getLogger()->logException($e);
				}
			}
			if(count($validSessions) === 0){
				unset($this->sessions[$xuid]);
				$player->kick("데이터를 로딩하던 중 오류가 발생했습니다. 나중에 다시 시도해주세요.");
				return;
			}
			$this->sessions[$xuid] = $validSessions;
			return;
		}

		$player->setNoClientPredictions(true);
		Await::f2c(function() use ($player) : Generator{
			try{
				if(!$player->isConnected()){
					return;
				}
				/** @phpstan-var list<Generator<mixed, mixed, mixed, SessionHandle<BaseSession>|null>> $generators */
				$generators = [];
				/** @phpstan-var list<Plugin> $plugins */
				$plugins = [];
				if(isset(self::$sessionCreateCallbacks)){
					foreach(self::$sessionCreateCallbacks as $plugin => $callback){
						$generators[] = $callback($player->getXuid(), $player, true);
						$plugins[] = $plugin;
					}
				}

				if(count($generators) === 0){
					$this->getLogger()->warning("No session providers registered! Player may not have full functionality.");
					$player->setNoClientPredictions(false);
					return;
				}

				/** @phpstan-var list<SessionHandle<BaseSession>|null> $handles */
				$handles = yield from Await::all($generators);
				foreach($handles as $index => $handle){
					if($handle === null){
						$player->kick("데이터를 로딩하던 중 오류가 발생했습니다. 나중에 다시 시도해주세요.");
						return;
					}
					$session = $handle->getSession();
					$this->sessions[$player->getXuid()][] = $session;
					$this->sessionToPlugin[$session] = $plugins[$index];
				}
				$player->setNoClientPredictions(false);
				if(!$player->isConnected()){
					if(isset($this->sessions[$player->getXuid()])){
						$sessions = $this->sessions[$player->getXuid()];
						foreach($sessions as $session){
							yield from $session->save(true);
						}
						unset($this->sessions[$player->getXuid()]);
					}
				}
			}catch(\Throwable $e){
				$this->getLogger()->logException($e);
				if($player->isConnected()){
					$player->kick("데이터를 로딩하던 중 오류가 발생했습니다. 나중에 다시 시도해주세요.");
				}
			}
		});
	}

	private function onPlayerQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();
		$xuid = $player->getXuid();
		if(!isset($this->sessions[$xuid])){
			return;
		}
		$sessions = $this->sessions[$xuid];
		unset($this->sessions[$xuid]);
		Await::f2c(function() use ($sessions) : Generator{
			try{
				foreach($sessions as $session){
					yield from $session->save(true);
					$plugin = $this->sessionToPlugin[$session] ?? null;
					if($plugin instanceof SessionProvider){
						yield from $plugin->removeSession($session->getXuid());
					}
					unset($this->sessionToPlugin[$session]);
				}
			}catch(\Throwable $e){
				$this->getLogger()->logException($e);
			}
		});
	}

	public static function safelyDisposeSession(?SessionHandle $handle, ?Plugin $ownedPlugin = null): Generator{
		$ownedPlugin ??= SessionManager::getInstance();
		if($handle !== null && !$handle->isDisposed() && $handle->getLifecycleState() !== SessionLifecycleState::DISPOSED){
			try{
				yield from $handle->dispose();
			}catch(\Throwable $e){
				$ownedPlugin->getLogger()->logException($e);
			}
		}
	}
}
