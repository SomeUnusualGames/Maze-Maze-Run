<?php
declare(strict_types=1);


class Text
{

	public $letterTexture;
	public array $letterOffset;
	public int $offsetX = 0;
	public int $offsetY = 0;

	private const BASE_SIZE = 6;
	private const SEPARATION = 3;

	public function __construct()
	{
		$letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789/";
		$offsetX = 0;
		for ($i = 0; $i < strlen($letters); $i++) {
			$this->letterOffset[$letters[$i]] = $offsetX;
			$offsetX += self::BASE_SIZE;
		}
	}

	public function __destruct()
	{
		SDL_DestroyTexture($this->letterTexture);
	}

	public function loadTexture($windowSurface, $renderer)
	{
		$image = SDL_LoadBMP("graphics/letters_nums.bmp");
		if ($image === null) {
			exit("Cannot load letters image." . PHP_EOL . SDL_GetError());
		}
		SDL_SetColorKey($image, true, SDL_MapRGB($windowSurface->format, 0, 0, 0));
		$this->letterTexture = SDL_CreateTextureFromSurface($renderer, $image);
		SDL_FreeSurface($image);
	}

	public function draw(
		string $text, int $x, int $y, $renderer,
		int $size = self::BASE_SIZE, int $sep = self::SEPARATION, bool $useOffset = true
	) {
		$origRect = new SDL_Rect;
		$origRect->y = 0;
		$origRect->w = self::BASE_SIZE;
		$origRect->h = self::BASE_SIZE;

		$destRect = new SDL_Rect;
		$destRect->x = $useOffset ? $x + $this->offsetX : $x;
		$destRect->y = $useOffset ? $y + $this->offsetY : $y;
		$destRect->w = $size;
		$destRect->h = $size;

		$text = strtoupper($text);
		for ($i = 0; $i < strlen($text); $i++) {
			$letter = $text[$i];
			if ($letter == " " || !array_key_exists($letter, $this->letterOffset)) {
				$destRect->x += $origRect->w + 2*$sep;
				continue;
			}
			$origRect->x = $this->letterOffset[$letter];
			SDL_RenderCopyEx($renderer, $this->letterTexture, $origRect, $destRect, 0.0, null, SDL_FLIP_NONE);
			$destRect->x += $destRect->w + $sep;
		}
	}
}