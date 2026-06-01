<?php

declare(strict_types=1);

namespace alvin0319\StarGateExtension;

use function exp;
use function min;

final readonly class ServerStat{

	// Constants for weights
	public const float WEIGHT_TPS = 0.40;     // Weight for TPS (20 is max TPS)
	public const float WEIGHT_AVGTPS = 0.30;  // Weight for average TPS
	public const float WEIGHT_PLAYERS = 0.15; // Weight for the number of players
	public const float WEIGHT_WORLDS = 0.10;  // Weight for the number of worlds
	public const float WEIGHT_UPTIME = 0.05;  // Weight for server uptime

	// Constants for TPS and Player normalization
	public const int MAX_TPS = 20;    // Max TPS (standard Minecraft server limit)
	public const int PLAYER_NORMALIZATION = 50;  // Player count normalization (50 is the threshold for ideal players)

	// Constants for sigmoid function
	public const float SIGMOID_PLAYER_K = 0.1;  // Steepness of player sigmoid curve
	public const int SIGMOID_WORLD_K = 1;    // Steepness of world sigmoid curve

	public const int IDEAL_PLAYER_COUNT = 50; // Ideal player count for server

	public function __construct(
		public string $name,
		public string $address,
		public int $players,
		public int $worlds,
		public float $tps,
		public float $avgTps,
		public int $uptime
	){}

	// Sigmoid function for decreasing values (e.g., players or worlds)
	private function sigmoidDecrease(int|float $x, int|float $x0, int|float $k) : float{
		return (float) (1 / (1 + exp($k * ($x - $x0))));  // Standard sigmoid function
	}

	// Score calculation function
	public function calculateScore() : float{
		// Normalize TPS (Max TPS = 20)
		$tpsScore = min($this->tps / self::MAX_TPS, 1);  // Max TPS normalization (0 to 1)
		$avgTpsScore = min($this->avgTps / self::MAX_TPS, 1);  // Max average TPS normalization (0 to 1)

		// Normalize Players using sigmoid (Ideal count: 50)
		// Players score decreases as player count increases
		$playerScore = $this->sigmoidDecrease($this->players, self::IDEAL_PLAYER_COUNT, self::SIGMOID_PLAYER_K);

		// Normalize Worlds using sigmoid (Ideal count: 1)
		// Worlds score decreases as world count increases
		$worldScore = $this->sigmoidDecrease($this->worlds, 1, self::SIGMOID_WORLD_K);

		// Uptime: Shorter uptime is better (inverse)
		$uptimeScore = 1 / (1 + $this->uptime);  // Inverse relationship to uptime (higher uptime = lower score)

		// Final score with weighted factors
		return
			($tpsScore * self::WEIGHT_TPS) +
			($avgTpsScore * self::WEIGHT_AVGTPS) +
			($playerScore * self::WEIGHT_PLAYERS) +
			($worldScore * self::WEIGHT_WORLDS) +
			($uptimeScore * self::WEIGHT_UPTIME);
	}

	public static function fromPacket(ServerStatPacket $packet) : ServerStat{
		return new ServerStat(
			$packet->getName(),
			$packet->getAddress(),
			$packet->getPlayers(),
			$packet->getWorlds(),
			$packet->getTps(),
			$packet->getAvgTps(),
			$packet->getUptime()
		);
	}
}
