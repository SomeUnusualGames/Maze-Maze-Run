<?php

declare(strict_types=1);

/**
 * Unused.
 * */

class Menu
{

	public $titleTexture;
	public $titleRect;

	public function __construct()
	{
		$this->titleRect = new SDL_Rect;
		$this->titleRect->x = 300;
		$this->titleRect->y = 300;
	}

	public function __destruct()
	{
		SDL_DestroyTexture($this->titleTexture);
	}

	public function loadTexture(string $titlePath, $windowSurface, $renderer)
	{
		$image = SDL_LoadBMP($titlePath);
		if ($image === null) {
			exit("Cannot load title image.");
		}
		SDL_SetColorKey($image, true, SDL_MapRGB($windowSurface->format, 0, 255, 0));
		$this->titleTexture = SDL_CreateTextureFromSurface($renderer, $image);
		$this->titleRect->w = $image->clip_rect->w;
		$this->titleRect->h = $image->clip_rect->h;
		SDL_FreeSurface($image);
	}

	public function draw($renderer)
	{
		SDL_RenderCopy($renderer, $this->titleTexture, null, $this->titleRect);
	}
}