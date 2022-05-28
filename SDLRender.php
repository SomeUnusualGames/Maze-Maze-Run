<?php
declare(strict_types=1);

class SDLRender
{
	public SDL_Window $window;
	public $renderer;
	public int $width;
	public int $height;

	public function __construct(string $title, int $width, int $height)
	{
		$this->width = $width;
		$this->height = $height;
		$this->window = SDL_CreateWindow(
			$title,
			SDL_WINDOWPOS_UNDEFINED, SDL_WINDOWPOS_UNDEFINED,
			$width, $height,
			SDL_WINDOW_SHOWN|SDL_WINDOW_RESIZABLE
		);
		if ($this->window === null) {
			exit("Cannot create window." . PHP_EOL . SDL_GetError());
		}
		$this->renderer = SDL_CreateRenderer($this->window, -1, 0);
		if ($this->renderer === null) {
			exit("Cannot create renderer." . PHP_EOL . SDL_GetError());
		}
		SDL_SetWindowMinimumSize($this->window, $width, $height);
	}

	public function __destruct()
	{
		SDL_DestroyRenderer($this->renderer);
		SDL_DestroyWindow($this->window);
		SDL_Quit();
	}

	public function clearBackground(int $r, int $g, int $b, int $a)
	{
		SDL_SetRenderDrawColor($this->renderer, $r, $g, $b, $a);
		SDL_RenderClear($this->renderer);
	}

	public function getWindowSurface()
	{
		return SDL_GetWindowSurface($this->window);
	}
}
