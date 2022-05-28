<?php
declare(strict_types=1);

require "animation.php";

enum State: int
{
	case Idle = 0;
	case Walking = 1;
	case Dead = 2;
}

class Player
{

	public Animation $animation;
	public State $state;
	public int $x;
	public int $y;
	public bool $canMove;
	public int $visibleRadius = DEBUG_MAZE ? PHP_INT_MAX : 8;

	public function __construct()
	{
		$this->state = State::Idle;
		$this->x = 0;
		$this->y = 0;
		$this->canMove = true;
	}

	public function loadAnimation(
		string $spritesheetPath, int $spriteScale, int $spriteWidth,
		int $spriteHeight, $windowSurface, $renderer
	) {
		$spriteRect = new SDL_Rect;
		$spriteRect->x = 0;
		$spriteRect->y = 0;
		$spriteRect->w = $spriteWidth;
		$spriteRect->h = $spriteHeight;

		$this->animation = new Animation(
			$spritesheetPath, $spriteScale, $spriteRect, $windowSurface, $renderer
		);

		$this->animation->createSprite(
			State::Idle->value, 0, 0, -1, [0.50, 0.35, 0.40, 0.50]
		);
		$this->animation->createSprite(
			State::Walking->value, 0, 14, 2, [0.05, 0.05, 0.05, 0.05]
		);
		$this->animation->createSprite(
			State::Dead->value, 0, 28, -1, [0.50, 0.35, 0.40, 0.50]
		);
	}

	public function update(float $dt)
	{
		$this->animation->update($dt);
		if ($this->animation->finished) {
			$this->animation->setSprite(State::Idle);
		}
	}

	public function isInPosition(int $x, int $y): bool
	{
		return $this->x == $x && $this->y == $y;
	}

	public function checkMovement($key, int &$offsetX, int &$offsetY)
	{
		switch ($key->keysym->sym) {
		case SDLK_w:
			$offsetY--;
			break;
		case SDLK_s:
			$offsetY++;
			break;
		case SDLK_d:
			$offsetX++;
			break;
		case SDLK_a:
			$offsetX--;
			break;
		}
	}

	public function setMovement(int $offsetX, int $offsetY)
	{
		if ($offsetX != 0) {
			$this->animation->flipFlag = $offsetX > 0 ? SDL_FLIP_NONE : SDL_FLIP_HORIZONTAL;
		}
		$this->x += $offsetX;
		$this->y += $offsetY;
		$this->animation->setSprite(State::Walking);
	}

	public function draw(int $tileSize, int $offsetX, int $offsetY, $windowSurface, $renderer)
	{
		$x = $offsetX + ($tileSize * $this->x) + 2*$this->animation->spriteRect->w;
		$y = $offsetY + ($tileSize * $this->y) + intdiv($tileSize, 4);
		$this->animation->draw($x, $y, $windowSurface, $renderer);
	}

	public function isInsideRadius(int $x, int $y): bool
	{
		//return abs($x-$this->x) <= 2 && abs($y-$this->y) <= 2;
		return pow($x - $this->x, 2) + pow($y - $this->y, 2) < $this->visibleRadius;
	}
}