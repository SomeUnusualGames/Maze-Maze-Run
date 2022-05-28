<?php
declare(strict_types=1);

enum Direction: int
{
	case North = 0;
	case South = 1;
	case East = 2;
	case West = 3;
	case NoDir = 4;
}

enum TileType: int
{
	case NoType = 0;
	case Walkable = 1;
}

class Tile
{
	public function __construct(
		public TileType $type,
		public Direction $direction,
		public array $borders
	) {}
}

class Maze
{
	public array $tiles;
	public $wallTexture;
	public SDL_Rect $wallClipRect;
	public int $offsetX = 0;
	public int $offsetY = 0;

	public Mix_Chunk $levelPassed;
	public SDL_Rect $exitRect;

	public function __construct(
		public int $width,
		public int $height,
		public int $tileSize,
		public int $level
	){
		$this->levelPassed = Mix_LoadWAV("sound/level-passed.wav");
		if ($this->levelPassed === null) {
			exit("Cannot load sound level-passed.wav" . PHP_EOL . Mix_GetError());
		}
	}

	public function __destruct()
	{
		SDL_DestroyTexture($this->wallTexture);
	}

	public function create()
	{
		$created = false;
		$tilesLeft = ($this->width * $this->height) - 1;

		$this->initMaze();

		// Wilson's algorithm
		// Get a tile and set it as part of the maze
		$tileMazeX = mt_rand(0, $this->width-1);
		$tileMazeY = mt_rand(0, $this->height-1);
		$this->tiles[$tileMazeY][$tileMazeX]->type = TileType::Walkable;

		while (!$created) {
			// Get a direction within bounds
			$initialX = mt_rand(0, $this->width-1);
			$initialY = mt_rand(0, $this->height-1);
			$this->setEmptyTile($initialX, $initialY);
			$nextX = $initialX;
			$nextY = $initialY;
			$possibleNextX = 0;
			$possibleNextY = 0;
			// Walk randomly through the maze until a walkable tile is found
			while ($this->tiles[$nextY][$nextX]->type != TileType::Walkable) {
				$newDir = Direction::from(mt_rand(0, 3));
				do {
					$possibleNextX = $nextX;
					$possibleNextY = $nextY;
					$newDir = Direction::from(mt_rand(0, 3));
					$this->moveCoords($possibleNextX, $possibleNextY, $newDir);
				} while (!$this->isPositionValid($possibleNextX, $possibleNextY));
				// Set the direction of the current tile and move to the next
				$this->tiles[$nextY][$nextX]->direction = $newDir;
				$nextX = $possibleNextX;
				$nextY = $possibleNextY;
			}
			// Walk from the initial tile, set them as walkable
			// and remove borders
			$currentX = $initialX;
			$currentY = $initialY;
			do {
				$this->tiles[$currentY][$currentX]->type = TileType::Walkable;
				$dir = $this->tiles[$currentY][$currentX]->direction;
				$tilesLeft--;
				// Remove form current and next tiles
				$this->tiles[$currentY][$currentX]->borders[$dir->value] = false;
				$this->moveCoords($currentX, $currentY, $dir);
				$opposite = $this->getOppositeDirection($dir);
				$this->tiles[$currentY][$currentX]->borders[$opposite->value] = false;
			} while ($this->tiles[$currentY][$currentX]->type != TileType::Walkable);

			if ($tilesLeft <= 0) {
				$created = true;
			}
		}

		$this->exitRect = new SDL_Rect;
		$this->exitRect->w = $this->tileSize;
		$this->exitRect->h = $this->tileSize;
		$randBorder = mt_rand(0, 100);
		if ($randBorder < 50) {
			$this->exitRect->x = $this->width - 1;
			$this->exitRect->y = mt_rand(0, $this->height-1);
		} else {
			$this->exitRect->x = mt_rand(0, $this->width-1);
			$this->exitRect->y = $this->height - 1;
		}
	}

	public function loadTexture($renderer)
	{
		$image = SDL_LoadBMP("graphics/bush.bmp");
		if ($image === null) {
			exit("Cannot load bush image." . PHP_EOL . SDL_GetError());
		}
		$this->wallTexture = SDL_CreateTextureFromSurface($renderer, $image);
		$this->wallClipRect = $image->clip_rect;
		SDL_FreeSurface($image);
	}

	public function canMove(int $currentX, int $currentY, int $offsetX, int $offsetY): bool
	{
		if (DEBUG_MAZE && !$this->isPositionValid($currentX+$offsetX, $currentY+$offsetY)) {
			return false;
		}
		$borders = $this->tiles[$currentY][$currentX]->borders;
		$dirRes = Direction::NoDir;
		if ($offsetX != 0) {
			$dirRes = $offsetX == 1 ? Direction::East : Direction::West;
		} elseif ($offsetY != 0) {
			$dirRes = $offsetY == 1 ? Direction::South : Direction::North;
		}
		if (!DEBUG_MAZE && ($dirRes == Direction::NoDir || $borders[$dirRes->value])) {
			return false;
		}

		$this->offsetX += $this->tileSize * (-$offsetX);
		$this->offsetY += $this->tileSize * (-$offsetY);

		return true;
	}

	public function drawBorder(int $x, int $y, Direction $dir, $renderer)
	{
		$destRect = new SDL_Rect;
		$destRect->w = $this->wallClipRect->w;
		$destRect->h = $this->wallClipRect->h;
		$angle = 0.0;
		$flip = SDL_FLIP_NONE;
		switch ($dir) {
		case Direction::North:
			if (DEBUG_MAZE) SDL_RenderDrawLine($renderer, $x, $y, $x+$this->tileSize, $y);
			$destRect->x = $x + intdiv($this->wallClipRect->h, 2);;
			$destRect->y = $y - intdiv($this->wallClipRect->h, 2);
			break;
		case Direction::South:
			if (DEBUG_MAZE) SDL_RenderDrawLine($renderer, $x, $y+$this->tileSize, $x+$this->tileSize, $y+$this->tileSize);
			$destRect->x = $x + intdiv($this->wallClipRect->h, 2);;
			$destRect->y = $y + $this->tileSize - intdiv($this->wallClipRect->h, 2);
			$flip = SDL_FLIP_VERTICAL;
			break;
		case Direction::East:
			if (DEBUG_MAZE) SDL_RenderDrawLine($renderer, $x+$this->tileSize, $y, $x+$this->tileSize, $y+$this->tileSize);
			$angle = 90.0;
			$destRect->x = $x + $this->tileSize - intdiv($this->wallClipRect->w, 2);
			$destRect->y = $y + intdiv($this->wallClipRect->w, 2);
			break;
		case Direction::West:
			if (DEBUG_MAZE) SDL_RenderDrawLine($renderer, $x, $y, $x, $y+$this->tileSize);
			$angle = 90.0;
			$destRect->x = $x - intdiv($this->wallClipRect->w, 2);
			$destRect->y = $y + intdiv($this->wallClipRect->w, 2);
			$flip = SDL_FLIP_VERTICAL;
			break;
		}
		if (!DEBUG_MAZE) SDL_RenderCopyEx($renderer, $this->wallTexture, null, $destRect, $angle, null, $flip);
	}

	public function drawExit($renderer, Text $text)
	{
		$exitRect = new SDL_Rect;
		$exitRect->x = 10 + $this->offsetX + $this->exitRect->x * $this->tileSize;
		$exitRect->y = 10 + $this->offsetY + $this->exitRect->y * $this->tileSize;
		$exitRect->w = $this->exitRect->w - 20;
		$exitRect->h = $this->exitRect->h - 20;
		SDL_SetRenderDrawColor($renderer, 0, 150, 0, 255);
		SDL_RenderFillRect($renderer, $exitRect);
		SDL_SetRenderDrawColor($renderer, 255, 255, 255, 255);
		$exitRect->x += intdiv($this->tileSize, 6);
		$exitRect->y += intdiv($this->tileSize, 3);
		$text->draw("EXIT", $exitRect->x, $exitRect->y, $renderer, 10, 3, false);
	}

	public function advanceLevel()
	{
		$this->level++;
		$this->width += 5;
		$this->height += 5;
	}

	public function getOppositeDirection(Direction $dir): Direction
	{
		return Direction::from(
			$dir->value&1 == 1 ? $dir->value-1 : $dir->value+1
		);
	}

	public function moveCoords(int &$x, int &$y, Direction $dir)
	{
		switch ($dir) {
		case Direction::North:
			$y--;
			break;
		case Direction::South:
			$y++;
			break;
		case Direction::East:
			$x++;
			break;
		case Direction::West:
			$x--;
			break;
		}
	}

	public function isPositionValid(int $x, int $y): bool
	{
		return $x >= 0 && $x < $this->width && $y >= 0 && $y < $this->height;
	}

	private function initMaze()
	{
		$this->tiles = [];
		for ($y=0; $y < $this->height; $y++) { 
			$this->tiles[] = [];
			for ($x=0; $x < $this->width; $x++) { 
				$this->tiles[$y][] = new Tile(TileType::NoType, Direction::NoDir, [true, true, true, true]);
			}
		}
	}

	private function setEmptyTile(int &$x, int &$y)
	{
		while ($this->tiles[$y][$x]->type != TileType::NoType) {
			$x = mt_rand(0, $this->width-1);
			$y = mt_rand(0, $this->height-1);
		}
	}
}
