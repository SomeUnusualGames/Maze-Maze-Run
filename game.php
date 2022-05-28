<?php
declare(strict_types=1);

define('DEBUG_MAZE', false);
define('FRAME', 1/60);

require "SDLRender.php";
require "text.php";
//require "menu.php";
require "maze.php";
require "enemy.php";
require "player.php";

class Game
{
	public SDLRender $SDLRender;
	public Text $text;
	//public Menu $menu;
	public Maze $maze;
	public array $enemyList;
	public Player $player;

	public bool $quit = false;
	public int $timeLeft = 21_666_666; // ~12 mins on my machine
	public int $activeEnemies = 0;

	public function __construct(string $title, int $width, int $height)
	{
		$this->SDLRender = new SDLRender($title, $width, $height);
		if (Mix_OpenAudio(44_100, MIX_DEFAULT_FORMAT, 2, 2048) < 0) {
			exit("Cannot open audio device." . PHP_EOL);
		}
	}

	public function initText()
	{
		$this->text = new Text;
		$this->text->loadTexture($this->SDLRender->getWindowSurface(), $this->SDLRender->renderer);
	}

	/*
	public function initMenu()
	{
		$this->menu = new Menu;
		$this->menu->loadTexture($this->SDLRender->getWindowSurface(), $this->SDLRender->renderer);
	}
	*/

	public function initCreateMaze(int $width, int $height, int $tileSize, int $level)
	{
		$this->maze = new Maze($width, $height, $tileSize, $level);
		$this->maze->loadTexture($this->SDLRender->renderer);
		$this->maze->create();

		$windowSurface = $this->SDLRender->getWindowSurface();
		$this->maze->offsetX = intdiv($windowSurface->w, 2) - intdiv($tileSize, 4);
		$this->maze->offsetY = intdiv($windowSurface->h, 2) - intdiv($tileSize, 4);
	}

	public function initEnemy(string $imagePath)
	{
		for ($id = 0; $id < 4; $id++) {
			$this->enemyList[] = new Enemy(
				$imagePath, $id, 0.3, $this->SDLRender->getWindowSurface(), $this->SDLRender->renderer
			);
			$this->enemyList[$id]->loadAudio();
		}
	}

	public function initPlayer()
	{
		$this->player = new Player;
		$this->player->loadAnimation(
			"graphics/ru.bmp", 3, 14, 14, $this->SDLRender->getWindowSurface(), $this->SDLRender->renderer
		);
		$this->player->animation->setSprite(State::Idle);
	}

	// ------ Game functions ------
	public function updateGame(float $dt)
	{
		for ($i = 0; $i < $this->activeEnemies; $i++) {
			$this->enemyList[$i]->update($dt, $this->maze, $this->player->x, $this->player->y, $this->player->canMove);
			if ($this->enemyList[$i]->gameover) {
				$this->player->canMove = false;
				$this->player->animation->setSprite(State::Dead);
			}
		}
		$this->player->update($dt);
		if ($this->player->isInPosition($this->maze->exitRect->x, $this->maze->exitRect->y)) {
			Mix_PlayChannel(-1, $this->maze->levelPassed, 0);
			if ($this->maze->level == 5) {
				SDL_ShowSimpleMessageBox(
					SDL_MESSAGEBOX_INFORMATION,
					"Game complete!",
					"Thank you for playing!",
					$this->SDLRender->window
				);
				$this->quit = true;
				return;
			}
			$this->maze->advanceLevel();
			$this->maze->create();
			$this->player->x = 0;
			$this->player->y = 0;
			$this->adjustOffset();
			$this->player->animation->setSprite(State::Idle);
			$this->player->animation->flipFlag = SDL_FLIP_NONE;

			$this->enemyList[$this->maze->level-2]->activate(
				$this->maze->width, $this->maze->height
			);
			$this->activeEnemies++;
		}
	}

	public function drawGame()
	{
		$this->SDLRender->clearBackground(0, 0, 0, 0);

		for ($i = 0; $i < $this->activeEnemies; $i++) {
			if (!$this->player->isInsideRadius($this->enemyList[$i]->x, $this->enemyList[$i]->y)) {
				$this->enemyList[$i]->isSeen = false;
				continue;
			}
			if (!$this->enemyList[$i]->isSeen and mt_rand(0, 100) < 3) {
				Mix_PlayChannel(-1, $this->enemyList[$i]->foundSound, 0);
				$this->enemyList[$i]->isSeen = true;
			}
			$this->enemyList[$i]->draw(
				$this->maze->tileSize,
				$this->maze->offsetX, $this->maze->offsetY,
				$this->SDLRender->getWindowSurface(), $this->SDLRender->renderer
			);
		}

		if ($this->player->isInsideRadius($this->maze->exitRect->x, $this->maze->exitRect->y)) {
			$this->maze->drawExit($this->SDLRender->renderer, $this->text);
		}
		for ($y=$this->maze->height-1; $y >= 0; $y--) {
			for ($x=$this->maze->width-1; $x >= 0; $x--) {
				for ($i=0; $i < 4; $i++) {
					if ($this->maze->tiles[$y][$x]->borders[$i] && $this->player->isInsideRadius($x, $y)) {
						$this->maze->drawBorder(
							$this->maze->offsetX + ($x*$this->maze->tileSize),
							$this->maze->offsetY + ($y*$this->maze->tileSize),
							Direction::from($i),
							$this->SDLRender->renderer
						);
					}
				}
			}
		}

		for ($i = 0; $i < $this->activeEnemies; $i++) {
			if ($this->enemyList[$i]->gameover) {
				SDL_RenderCopyEx(
					$this->SDLRender->renderer, $this->enemyList[$i]->gotPlayerTexture,
					$this->enemyList[$i]->gotPlayerRect, null, 0.0, null, SDL_FLIP_NONE
				);
				$this->text->draw("PRESS R TO RESTART", 0, $this->SDLRender->height-60, $this->SDLRender->renderer, 24);
			}
		}

		$this->player->draw(
			$this->maze->tileSize, $this->maze->offsetX, $this->maze->offsetY,
			$this->SDLRender->getWindowSurface(), $this->SDLRender->renderer
		);

		if ($this->player->canMove) {
			$this->timeLeft -= 1000;
			if ($this->timeLeft <= 0) {
				SDL_ShowSimpleMessageBox(
					SDL_MESSAGEBOX_INFORMATION,
					"Time's up!",
					"Better luck next time!",
					$this->SDLRender->window
				);
				$this->quit = true;
				return;
			}
			SDL_SetRenderDrawColor($this->SDLRender->renderer, 255, 255, 255, 255);
			$this->text->draw($this->formatTimer($this->timeLeft), 0, 0, $this->SDLRender->renderer, 24);
			SDL_SetRenderDrawColor($this->SDLRender->renderer, 255, 255, 255, 255);
			$this->text->draw(
				"Level " . strval($this->maze->level) . "/5",
				0, $this->SDLRender->height-24, 
				$this->SDLRender->renderer,
				24
			);
		}
		SDL_RenderPresent($this->SDLRender->renderer);
	}

	public function eventsGame($event)
	{
		while (SDL_PollEvent($event)) {
			switch ($event->type) {
			case SDL_QUIT:
				$this->quit = true;
				break 2;
			case SDL_KEYDOWN:
				if ($event->key->keysym->sym == SDLK_F4) {
					$isFullscreen = SDL_GetWindowFlags($this->SDLRender->window) & SDL_WINDOW_FULLSCREEN_DESKTOP;
					$flag = $isFullscreen ? 0 : SDL_WINDOW_FULLSCREEN_DESKTOP;
					SDL_SetWindowFullscreen($this->SDLRender->window, $flag);
					break;
				} elseif ($event->key->keysym->sym == SDLK_ESCAPE) {
					$this->quit = true;
					break 2;
				} elseif ($event->key->keysym->sym == SDLK_r && !$this->player->canMove) {
					$this->player->x = 0;
					$this->player->y = 0;
					$this->adjustOffset();
					$this->player->animation->setSprite(State::Idle);
					$this->player->animation->flipFlag = SDL_FLIP_NONE;
					$this->player->canMove = true;
					for ($i = 0; $i < $this->activeEnemies; $i++) {
						$this->enemyList[$i]->gameover = false;
					}
				} else {
					if ($this->playerMovement($event->key) and $this->activeEnemies > 0) {
						for ($i = 0; $i < $this->activeEnemies; $i++) {
							$this->enemyList[$i]->canMove = true;
						}
					}
				}
				break;
			case SDL_WINDOWEVENT:
				if ($event->window->event == SDL_WINDOWEVENT_SIZE_CHANGED) {
					$this->adjustOffset();
				}
				break;
			}
		}

	}

	// ------ End Game functions ------

	public function run()
	{
		$event = new SDL_Event;
		while (!$this->quit) {
			$this->eventsGame($event);
			$this->updateGame(FRAME);
			$this->drawGame();

			SDL_Delay(intdiv(1000, 60));
		}
	}

	private function adjustOffset()
	{
		$windowSurface = $this->SDLRender->getWindowSurface();

		$offsetX = $this->player->x * $this->maze->tileSize;
		$offsetY = $this->player->y * $this->maze->tileSize;

		$newX = intdiv($windowSurface->w, 2);
		$newY = intdiv($windowSurface->h, 2);

		$this->maze->offsetX = $newX - intdiv($this->maze->tileSize, 4) - $offsetX;
		$this->maze->offsetY = $newY - intdiv($this->maze->tileSize, 4) - $offsetY;

		$this->text->offsetX = $newX - intdiv($this->SDLRender->width, 2);
		$this->text->offsetY = $newY - intdiv($this->SDLRender->height, 2);
		//print($this->text->offsetX . " " . $this->text->offsetY . PHP_EOL);
	}

	private function playerMovement($key): bool
	{
		if (!$this->player->canMove) return false;
		$offsetX = 0;
		$offsetY = 0;
		$this->player->checkMovement($key, $offsetX, $offsetY);
		$canMove = $this->maze->canMove($this->player->x, $this->player->y, $offsetX, $offsetY);
		if ($canMove) {
			$this->player->setMovement($offsetX, $offsetY);
		}
		return $canMove;
	}

	private function formatTimer(): string
	{
		$s = floor(($this->timeLeft / 1000) % 60);
		$m = floor(($this->timeLeft / 60_000) % 60);
		$h = floor(($this->timeLeft / 3_600_000));
		$ss = $s < 10 ? "0" . $s : $s;
		$mm = $m < 10 ? "0" . $m : $m;
		$hh = $h < 10 ? "0" . $h : $h;
		return $hh . " " . $mm . " " . $ss;
	}

}
