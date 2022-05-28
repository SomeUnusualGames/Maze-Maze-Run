<?php
declare(strict_types=1);

class Enemy
{
	public int $id;
	public int $x;
	public int $y;

	public float $movTimer;
	public float $currentTimer;

	public bool $active = false;
	public bool $canMove = false;
	public bool $moveWithPlayer = true;
	public bool $isSeen = false;
	public bool $gameover = false;
	public Animation $animation;
	
	public Mix_Chunk $foundSound;
	public Mix_Chunk $loudSound;
	public $gotPlayerTexture;
	public SDL_Rect $gotPlayerRect;

	private array $directionsAvailable;
	private array $distances;
	private array $positionSeen;
	private int $objectiveX = 0;
	private int $objectiveY = 0;
	private Direction $oppositeDirection = Direction::NoDir;
	private Direction $directionToMove = Direction::NoDir;

	public function __construct(string $imagePath, int $id, float $timer, $windowSurface, $renderer)
	{
		$this->id = $id;
		$this->x = 0;
		$this->y = 0;
		$this->movTimer = $timer;
		$this->currentTimer = $timer;

		$this->directionsAvailable = [];
		$this->distances = [];
		$this->positionSeen = [];

		$origRect = new SDL_Rect;
		$origRect->x = 0;
		$origRect->y = $id * 14;
		$origRect->w = 14;
		$origRect->h = 14;

		if ($id == 2) {
			$this->moveWithPlayer = false;
			$this->currentTimer *= 3;
			$this->movTimer *= 3;
		} elseif ($id == 3) {
			$this->moveWithPlayer = false;
			$this->currentTimer /= 3;
			$this->movTimer /= 3;
		}

		$this->animation = new Animation($imagePath, 3, $origRect, $windowSurface, $renderer);
		$this->animation->createSprite(0, $origRect->x, $origRect->y, -1, [$timer, $timer]);
		$this->animation->setSprite(0);

		$image = SDL_LoadBMP("graphics/fants_gotcha.bmp");
		if ($image === null) {
			exit("Cannot load enemy image" . PHP_EOL . SDL_GetError());
		}
		SDL_SetColorKey($image, true, SDL_MapRGB($windowSurface->format, 0, 255, 0));
		$this->gotPlayerTexture = SDL_CreateTextureFromSurface($renderer, $image);
		SDL_FreeSurface($image);

		$this->gotPlayerRect = new SDL_Rect;
		$this->gotPlayerRect->x = 0;
		$this->gotPlayerRect->y = $id * 304;
		$this->gotPlayerRect->w = 300;
		$this->gotPlayerRect->h = 304;
	}

	public function loadAudio()
	{
		$path = match ($this->id) {
			0 => "sound/qubodup-GhostMoan02.wav",
			1 => "sound/zombie_pain.wav",
			2 => "sound/qubodup-GhostMoan03.wav",
			3 => "sound/scream_horror1.wav",
			default => ""
		};
		$this->foundSound = Mix_LoadWAV($path);
		if ($this->foundSound === null) {
			exit("Cannot load enemy sound " . $path . PHP_EOL . Mix_GetError());
		}
		$this->loudSound = Mix_LoadWAV("sound/610753_prankheiteneberex_loud-jumpscare.wav");
		if ($this->loudSound === null) {
			exit("Cannot load enemy loud sound" . PHP_EOL . Mix_GetError());
		}
	}

	public function activate(int $mazeWidth, int $mazeHeight)
	{
		$this->active = true;
		$this->x = mt_rand(intdiv($mazeWidth, 2), $mazeWidth-1);
		$this->y = mt_rand(intdiv($mazeHeight, 2), $mazeHeight-1);
	}

	public function playerInSameLine(Maze $maze, int $playerX, int $playerY): bool
	{
		foreach ($this->directionsAvailable as $dirValue) {
			$initialX = $this->x;
			$initialY = $this->y;
			$keepMoving = true;
			// Move until reaching a border
			while ($keepMoving) {
				$maze->moveCoords($initialX, $initialY, Direction::from($dirValue));
				if ($initialX == $playerX && $initialY == $playerY) {
					$this->directionToMove = Direction::from($dirValue);
					$this->oppositeDirection = $maze->getOppositeDirection($this->directionToMove);
					$this->objectiveX = $initialX;
					$this->objectiveY = $initialY;
					return true;
				}
				if ($maze->tiles[$initialY][$initialX]->borders[$dirValue]) {
					$keepMoving = false;
				}
			}
		}
		return false;
	}

	public function setMovementValues(Maze $maze, int $directionToMove, int $x, int $y)
	{
		$this->directionToMove = Direction::from($directionToMove);
		$this->oppositeDirection = $maze->getOppositeDirection($this->directionToMove);
		$this->objectiveX = $x;
		$this->objectiveY = $y;
	}

	public function setDirection0_1(Maze $maze, int $playerX, int $playerY)
	{
		$this->directionsAvailable = [];
		$this->distances = [];

		// Get all available directions to move
		for ($i = 0; $i < 4; $i++) {
			if (!$maze->tiles[$this->y][$this->x]->borders[$i]) {
				$this->directionsAvailable[] = $i;
			}
		}

		// Only one movement available, move there
		if (count($this->directionsAvailable) == 1) {
			$this->directionToMove = Direction::from($this->directionsAvailable[0]);
			$this->oppositeDirection = $maze->getOppositeDirection($this->directionToMove);
			$this->objectiveX = $this->x;
			$this->objectiveY = $this->y;
			$maze->moveCoords($this->objectiveX, $this->objectiveY, $this->directionToMove);
			return;
		}

		// Check if the player can be reached by moving in a single direction
		if ($this->playerInSameLine($maze, $playerX, $playerY)) {
			return;
		}

		// Remove the opposite direction from the list
		// to avoid moving back and forth
		$keyOpposite = array_search($this->oppositeDirection->value, $this->directionsAvailable);
		if ($keyOpposite !== false) {
			unset($this->directionsAvailable[$keyOpposite]);
			// Rearrange array
			$this->directionsAvailable = array_values($this->directionsAvailable);
		}

		foreach ($this->directionsAvailable as $dir) {
			$this->distances[$dir] = [
				"dist" => INF,
				"x" => 0,
				"y" => 0,
				"dir" => Direction::NoDir
			];
		}

		// Only one movement available, move there (again)
		if (count($this->directionsAvailable) == 1) {
			$toMove = $this->directionsAvailable[0];
			$x = $this->x;
			$y = $this->y;
			$maze->moveCoords($x, $y, Direction::from($toMove));
			$this->setMovementValues($maze, $toMove, $x, $y);
			return;
		}

		// Check the closest position to the player
		foreach ($this->directionsAvailable as $dirValue) {
			$initialX = $this->x;
			$initialY = $this->y;
			$keepMoving = true;
			$count = 0;
			$foundIntersection = false;
			$opposite = $maze->getOppositeDirection(Direction::from($dirValue));

			// Check if this path has an intersection,
			// if it doesn't, is a dead end
			while ($keepMoving) {
				$maze->moveCoords($initialX, $initialY, Direction::from($dirValue));
				$distX = $playerX;
				$distY = $playerY;
				if ($this->id == 1) {
					if (mt_rand(0, 100) < 50) {
						$distX = $maze->exitRect->x;
						$distY = $maze->exitRect->y;
					}
				}
				// Get smallest distance
				$dist = $this->getDistance($initialX, $initialY, $distX, $distY);
				if (!isset($this->distances[$dirValue])) {
					return;
				}
				if ($dist < $this->distances[$dirValue]["dist"]) {
					$this->distances[$dirValue]["dist"] = $dist;
					$this->distances[$dirValue]["x"] = $initialX;
					$this->distances[$dirValue]["y"] = $initialY;
					$this->distances[$dirValue]["dir"] = Direction::from($dirValue);
				}
				// Check for an intersection
				if (!$foundIntersection) {
					for ($i = 0; $i < 4; $i++) {
						if ($i == $dirValue) {
							continue;
						}
						$tempX = $initialX;
						$tempY = $initialY;
						$maze->moveCoords($tempX, $tempY, Direction::from($i));
						if (!$maze->isPositionValid($tempX, $tempY)) {
							continue;
						}
						if (!$maze->tiles[$tempY][$tempX]->borders[$i]) {
							$foundIntersection = true;
							break;
						}
					}
				}
				if (!$maze->isPositionValid($initialX, $initialY)) {
					$keepMoving = false;
					break;
				}
				if ($maze->tiles[$initialY][$initialX]->borders[$dirValue]) {
					$keepMoving = false;
				}
			}

			// If no intersection is found discard this direction
			/*
			if (!$foundIntersection) {
				$keyDist = array_search($dirValue, $this->distances);
				unset($this->distances[$keyDist]);
				$this->distances = array_values($this->distances);

				$keyDir = array_search($dirValue, $this->directionsAvailable);
				unset($this->directionsAvailable[$keyDir]);
				$this->directionsAvailable = array_values($this->directionsAvailable);
			}

			if (count($this->distances) == 1) {
				$toMove = $this->directionsAvailable[0];
				$x = $this->x;
				$y = $this->y;
				$maze->moveCoords($x, $y, Direction::from($toMove));
				$this->setMovementValues($maze, $toMove, $x, $y);
				return;
			}
			*/
		}

		// Get smallest distance
		$minDist = ["dist" => INF, "x" => 0, "y" => 0, "dir" => Direction::NoDir];
		foreach ($this->distances as $dist) {
			if ($dist["dist"] < $minDist["dist"]) {
				$minDist["dist"] = $dist["dist"];
				$minDist["x"]    = $dist["x"];
				$minDist["y"]    = $dist["y"];
				$minDist["dir"]  = $dist["dir"];
			}
		}
		$this->setMovementValues($maze, $minDist["dir"]->value, $minDist["x"], $minDist["y"]);
	}

	public function setDirection2(Maze $maze, int $playerX, int $playerY)
	{
		$dir = match (true) {
			$this->y > $playerY => Direction::North,
			$this->x < $playerX => Direction::East,
			$this->y < $playerY => Direction::South,
			$this->x > $playerX => Direction::West,
			default => Direction::NoDir
		};
		$x = $this->x;
		$y = $this->y;
		$maze->moveCoords($x, $y, $dir);
		$this->setMovementValues($maze, $dir->value, $x, $y);
	}

	public function setDirection3(Maze $maze, int $playerX, int $playerY)
	{
		$this->directionsAvailable = [];
		for ($i = 0; $i < 4; $i++) {
			if (!$maze->tiles[$this->y][$this->x]->borders[$i]) {
				$this->directionsAvailable[] = $i;
			}
		}
		shuffle($this->directionsAvailable);
		$newDir = $this->directionsAvailable[array_rand($this->directionsAvailable)];
		$x = $this->x;
		$y = $this->y;
		$maze->moveCoords($x, $y, Direction::from($newDir));
		$this->setMovementValues($maze, $newDir, $x, $y);
	}

	public function update(float $dt, Maze $maze, int $playerX, int $playerY, bool $canMove)
	{
		$this->animation->update($dt);

		if (!$canMove) return;

		$this->currentTimer -= $dt;
		if ($this->currentTimer <= 0 || ($this->moveWithPlayer && $this->canMove)) {
			if ($this->directionToMove == Direction::NoDir) {
				// This is horrible but much shorter than writing ifs/switch/match
				$dirFunc = $this->id <= 1 ? "setDirection0_1" : "setDirection" . strval($this->id);
				$this->$dirFunc($maze, $playerX, $playerY);
			}
			$this->playerInSameLine($maze, $playerX, $playerY);
			$maze->moveCoords($this->x, $this->y, $this->directionToMove);
			if ($this->x == $this->objectiveX && $this->y == $this->objectiveY) {
				$this->directionToMove = Direction::NoDir;
				$this->objectiveX = 0;
				$this->objectiveY = 0;
			}
			$this->currentTimer = $this->movTimer;
			$this->canMove = false;
		}
		if ($this->x == $playerX && $this->y == $playerY && !$this->gameover) {
			$this->gameover = true;
			Mix_PlayChannel(-1, $this->loudSound, 0);
		}
	}

	public function draw(int $tileSize, int $offsetX, int $offsetY, $windowSurface, $renderer)
	{
		$x = $offsetX + ($tileSize * $this->x) + 2*$this->animation->spriteRect->w;
		$y = $offsetY + ($tileSize * $this->y) + intdiv($tileSize, 4);
		$this->animation->draw($x, $y, $windowSurface, $renderer);
	}

	public function getDistance(int $x, int $y, int $otherX, int $otherY): float
	{
		return sqrt(pow($x - $otherX, 2) + pow($y - $otherY, 2));
	}
}