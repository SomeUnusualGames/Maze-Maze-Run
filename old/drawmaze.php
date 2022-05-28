<?php

require "maze.php";

$window = SDL_CreateWindow("Maze", SDL_WINDOWPOS_UNDEFINED, SDL_WINDOWPOS_UNDEFINED, 800, 640, SDL_WINDOW_SHOWN);
$renderer = SDL_CreateRenderer($window, 0, SDL_RENDERER_SOFTWARE);

function drawBorder(int $x, int $y, Direction $dir, int $tileSize, $renderer)
{
	SDL_SetRenderDrawColor($renderer, 255, 255, 255, 255);
	switch ($dir) {
	case Direction::North:
		SDL_RenderDrawLine($renderer, $x, $y, $x+$tileSize, $y);
		break;
	case Direction::South:
		SDL_RenderDrawLine($renderer, $x, $y+$tileSize, $x+$tileSize, $y+$tileSize);
		break;
	case Direction::East:
		SDL_RenderDrawLine($renderer, $x+$tileSize, $y, $x+$tileSize, $y+$tileSize);
		break;
	case Direction::West:
		SDL_RenderDrawLine($renderer, $x, $y, $x, $y+$tileSize);
		break;
	}
}


$maze = new Maze(30, 30, 1);
$tileSize = 32;
$maze->create();

$image = SDL_LoadBMP("test.bmp");
$color = SDL_MapRGB(SDL_GetWindowSurface($window)->format, 0xff, 0xff, 0xff);
SDL_SetColorKey($image, true, $color);

$texture = SDL_CreateTextureFromSurface($renderer, $image);
$drect = $image->clip_rect;
SDL_FreeSurface($image);

$rotCenter = new SDL_Point(10, 10);
$destRect = new SDL_Rect;
$destRect->x = $x = 100;
$destRect->y = $y = 100;
$destRect->w = 64;
$destRect->h = 64;


$quit = false;
$event = new SDL_Event;

while (!$quit) {
	SDL_PollEvent($event);
	SDL_Delay(30);

	SDL_SetRenderDrawColor($renderer, 0, 0, 255, 255);
	SDL_RenderClear($renderer);

	for ($y=0; $y < $maze->height; $y++) { 
		for ($x=0; $x < $maze->width; $x++) { 
			for ($i=0; $i < 4; $i++) { 
				if ($maze->tiles[$y][$x]->borders[$i]) {
					//drawBorder($x*$tileSize, $y*$tileSize, Direction::from($i), $tileSize, $renderer);
				}
			}
		}
	}

	SDL_RenderCopyEx($renderer, $texture, NULL, $destRect, 90, $rotCenter, SDL_FLIP_NONE);

	SDL_RenderPresent($renderer);

	while (SDL_PollEvent($event)){
		if ($event->type == SDL_QUIT) $quit = true;
		if ($event->type == SDL_KEYDOWN) {
			$maze->create();
		}
	}
}

SDL_DestroyTexture($texture);
SDL_DestroyRenderer($renderer);
SDL_DestroyWIndow($window);
SDL_Quit();
