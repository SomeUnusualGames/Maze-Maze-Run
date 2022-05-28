<?php
declare(strict_types=1);

class Animation
{

	public $spritesheetTexture;
	public SDL_Rect $spriteRect;
	public SDL_Rect $destRect;
	public int $flipFlag = SDL_FLIP_NONE;

	public int $spriteId;
	public int $spriteScale;
	public int $delayIndex;
	public bool $finished;
	public int $loopCount;
	public float $delay;
	public array $spriteList;
	public array $currentSprite;

	public function __construct(string $spritesheet, int $spriteScale, $spriteRect, $windowSurface, $renderer)
	{
		$this->spriteRect = $spriteRect;
		$this->spriteScale = $spriteScale;
		$this->destRect = new SDL_Rect;
		$this->spriteList = [];
		$this->finished = false;
		$this->loopCount = 0;

		$image = SDL_LoadBMP($spritesheet);
		if ($image === null) {
			exit("Cannot load spritesheet." . PHP_EOL . SDL_GetError());
		}
		SDL_SetColorKey($image, true, SDL_MapRGB($windowSurface->format, 0, 255, 0));
		$this->spritesheetTexture = SDL_CreateTextureFromSurface($renderer, $image);
		SDL_FreeSurface($image);
	}

	public function __destruct()
	{
		SDL_DestroyTexture($this->spritesheetTexture);
	}

	public function createSprite(int $id, int $originX, int $originY, int $loopCount, array $delayList)
	{
		$this->spriteList[$id] = [
			"originX" => $originX,
			"originY" => $originY,
			"loopCount" => $loopCount,
			"delayList" => $delayList
		];
	}

	public function setSprite($id)
	{
		$value = gettype($id) == "integer" ? $id : $id->value;
		if (isset($this->spriteId) && $this->spriteId == $value) {
			$this->finished = false;
			$this->loopCount = $this->currentSprite["loopCount"];
			return;
		}
		$this->currentSprite = $this->spriteList[$value];
		$this->spriteId = $value;
		$this->delayIndex = 0;
		$this->delay = $this->currentSprite["delayList"][0];
		$this->spriteRect->x = $this->currentSprite["originX"];
		$this->spriteRect->y = $this->currentSprite["originY"];
		$this->loopCount = $this->currentSprite["loopCount"];
		$this->finished = false;
	}

	public function update(float $dt)
	{
		if ($this->finished) return;

		$this->delay -= $dt;
		if ($this->delay <= 0) {
			$this->delayIndex++;
			// Reset the animation or move to the next frame
			if ($this->delayIndex == count($this->currentSprite["delayList"])) {
				$this->loopCount--;
				$this->delayIndex = 0;
				$this->spriteRect->x = $this->currentSprite["originX"];
				$this->finished = $this->loopCount == 0;
			} else {
				$this->spriteRect->x = $this->spriteRect->x + $this->spriteRect->w;
			}
			$this->delay = $this->currentSprite["delayList"][$this->delayIndex];
		}
	}

	public function draw(int $x, int $y, $windowSurface, $renderer)
	{
		$this->destRect->x = $x; //intdiv($windowSurface->w, 2);
		$this->destRect->y = $y; //intdiv($windowSurface->h, 2);
		$this->destRect->w = $this->spriteScale * $this->spriteRect->w;
		$this->destRect->h = $this->spriteScale * $this->spriteRect->h;
		SDL_RenderCopyEx($renderer, $this->spritesheetTexture, $this->spriteRect, $this->destRect, 0.0, null, $this->flipFlag);
	}
}