<?php
declare(strict_types=1);

require "game.php";

$game = new Game("Maze Maze Run", 960, 640);
$game->initText();
$game->initCreateMaze(10, 10, 100, 1);
$game->initEnemy("graphics/fants.bmp");
$game->initPlayer();
$game->run();
