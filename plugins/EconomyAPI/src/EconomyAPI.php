<?php

declare(strict_types=1);

namespace alvin0319\EconomyAPI;

use alvin0319\EconomyAPI\command\CurrencyCommand;
use alvin0319\EconomyAPI\command\GiveMoneyCommand;
use alvin0319\EconomyAPI\command\MyMoneyCommand;
use alvin0319\EconomyAPI\command\PayCommand;
use alvin0319\EconomyAPI\command\SetMoneyCommand;
use alvin0319\EconomyAPI\command\TakeMoneyCommand;
use alvin0319\EconomyAPI\command\TopMoneyCommand;
use alvin0319\EconomyAPI\data\Balance;
use alvin0319\EconomyAPI\data\Currency;
use alvin0319\EconomyAPI\model\BalanceUpdateModel;
use alvin0319\EconomyAPI\model\CurrencyUpdateModel;
use alvin0319\SessionManager\OfflineSessionHandle;
use alvin0319\SessionManager\OnlineSessionHandle;
use alvin0319\SessionManager\SessionManager;
use alvin0319\SessionManager\SessionProvider;
use alvin0319\SessionManager\SessionProviderTrait;
use alvin0319\StarGatePolyfill\StarGatePolyfill;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use alvin0319\EconomyAPI\registry\CurrencyRegistry;
use alvin0319\EconomyAPI\service\EconomyService;
use alvin0319\EconomyAPI\session\EconomySession;
use skh6075\ExtensionPlugin\ExtensionPlugin;
use SOFe\AwaitGenerator\Await;

/**
 * @phpstan-implements SessionProvider<EconomySession>
 */
final class EconomyAPI extends PluginBase implements SessionProvider{
	use SingletonTrait;

	/** @use SessionProviderTrait<EconomySession> */
	use SessionProviderTrait;

	public const string IDENTIFIER_CURRENCY_UPDATE = "economy:currency_update";
	public const string IDENTIFIER_BALANCE_UPDATE = "economy:balance_update";

	private DataConnector $connector;

	private EconomyService $service;
	private CurrencyRegistry $currencyRegistry;

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->connector = ExtensionPlugin::createConnector($this);
		$this->service = new EconomyService($this->connector);
		$this->currencyRegistry = new CurrencyRegistry();
		Await::f2c(function(): \Generator{
			yield from $this->service->init();

			$currencies = yield from $this->service->loadCurrencies();
			foreach($currencies as $currency){
				$this->currencyRegistry->register($currency);
			}

			yield from $this->currencyRegistry->setup($this->service);
		});
		$this->connector->waitAll();

		SessionManager::registerSessionCreate($this);
		$this->registerNotify();

		$this->getServer()->getCommandMap()->registerAll(strtolower($this->getName()), [
			new CurrencyCommand($this->service, $this->currencyRegistry),
			new GiveMoneyCommand($this->currencyRegistry),
			new TakeMoneyCommand($this->currencyRegistry),
			new SetMoneyCommand($this->currencyRegistry),
			new TopMoneyCommand($this->service, $this->currencyRegistry),
			new PayCommand($this->currencyRegistry),
			new MyMoneyCommand()
		]);
	}

	protected function onDisable() : void{
		Await::f2c(function(): \Generator{
			$sessions = $this->sessions;
			$this->sessions = [];

			foreach($sessions as $session){
				try{
					yield from $session->save(true);
				}catch(\Throwable $e){
					$this->getLogger()->logException($e);
				}
			}
		});
		$this->connector->waitAll();
		$this->connector->close();
	}

	private function registerNotify(): void{
		StarGatePolyfill::registerUpdateNotify(
			identifier: self::IDENTIFIER_CURRENCY_UPDATE,
			class: CurrencyUpdateModel::class,
			handler: $this->currencyRegistry->onUpdate(...)
		);
		StarGatePolyfill::registerUpdateNotify(
			identifier: self::IDENTIFIER_BALANCE_UPDATE,
			class: BalanceUpdateModel::class,
			handler: $this->handleBalanceUpdate(...)
		);
	}

	public function getService(): EconomyService{
		return $this->service;
	}

	public function getCurrencyRegistry(): CurrencyRegistry{
		return $this->currencyRegistry;
	}

	public function getDefaultCurrency(): Currency{
		return $this->currencyRegistry->getDefaultCurrency();
	}

	public function createSession(string $xuid, ?Player $player = null, bool $createOnFailure = false) : \Generator{
		if(isset($this->sessions[$xuid])){
			$session = $this->sessions[$xuid];
			if($player !== null && !$session->isOnline()){
				$session->switchOnline($player);
			}
			return $player !== null
				? new OnlineSessionHandle($session)
				: new OfflineSessionHandle($session, $this, $this);
		}
		try{
			$balances = yield from $this->service->loadBalances($xuid);

			$currencies = [];
			foreach($this->currencyRegistry->getAll() as $currency){
				$balance = $balances[$currency->id] ?? null;
				if($balance === null && $createOnFailure){
					$balance = $currency->defaultBalance;
					yield from $this->service->saveBalance($xuid, $currency->id, $balance);
				}
				if($balance !== null){
					$currencies[$currency->id] = new Balance($currency, $balance);
				}
			}
			if($currencies === [] && !$createOnFailure){
				return null;
			}

			$session = new EconomySession($xuid, $player, $currencies);
			$this->sessions[$xuid] = $session;

			return $player !== null
				? new OnlineSessionHandle($session)
				: new OfflineSessionHandle($session, $this, $this);
		}catch(\Throwable $e){
			$this->getLogger()->logException($e);
			return null;
		}
	}
	/**
	 * @safe-generator
	 *
	 * @phpstan-return \Generator<mixed, mixed, mixed, void>
	 */
	private function handleBalanceUpdate(BalanceUpdateModel $model): \Generator{
		try{
			$session = $this->getSession($model->xuid);
			if($session === null){
				return;
			}
			$currency = $this->currencyRegistry->find($model->currencyId);
			if($currency === null){
				return;
			}
			$balance = yield from $this->service->getBalance($model->xuid, $currency);
			if($balance === null){
				return;
			}

			$session->getBalance($currency)->setAmount($balance);
		}catch(\Throwable $e){
			$this->getLogger()->logException($e);
		}
	}
}