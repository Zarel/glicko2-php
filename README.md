glicko2-php
===========

A PHP implementation of [Glicko2][1], a rating system for game ladders.
Glicko2 is a refinement of the well-known [Elo][2] rating system that adds
the concepts of rating deviation, volatility, and rating decay.

The original glicko2phplib was written by Noah Smith on 2011 June 7. That
version contains significant mathematical errors. This is a fork by Guangcong
Luo on 2012 Sept 28 that corrects these errors, and also updates it to be
compatible with PHP 5. It should also work on PHP 4, although I have not
tested that.

 [1]: http://en.wikipedia.org/wiki/Glicko_rating_system
 [2]: http://en.wikipedia.org/wiki/Elo_rating_system

Usage
-----

	Glicko2Player([$rating = 1500 [, $rd = 350 [, $volatility = 0.06 [, $mu [, $phi [, $sigma [, $systemconstant = 0.75 ]]]]]]])

For new players, use the default values for `rating`, `rd`, and `volatility`.

The `systemconstant` should be between 0.3 and 1.2, depending on system itself
(this is game dependent, and must be set by estimation or experimentation)

Updating a Glicko2Player
------------------------

Add wins, losses, and draws to a player:

	$Alice = new Glicko2Player();
	$Bob = new Glicko2Player();
	$Charlie = new Glicko2Player();

	$Alice->AddWin($Bob);
	$Alice->AddWin($Charlie)

	$Bob->AddLoss($Alice);
	$Bob->AddWin($Charlie);

	$Charlie->AddLoss($Alice);
	$Charlie->AddLoss($Bob);

	$Alice->Update();
	$Bob->Update();
	$Charlie->Update();
