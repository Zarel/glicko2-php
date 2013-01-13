<?php

/*******************************************************************************

Corrected implementation of Glicko2

License: LGPLv3

Modified by Guangcong Luo on 2012 Sept 28
<guangcongluo@gmail.com>

Modified from Noah Smith's implementation, which contains a number of mathematical
errors.

His original documentation is reproduced below.

*******************************************************************************

glicko-2 ranking system

Written by Noah Smith 2011, June 7
megiddo ( @t ) thirdform ( dot ) com

Based on http://www.glicko.net/glicko/glicko2.doc/example.html

Usage
Glicko2Player([$rating = 1500 [, $rd = 350 [, $volatility = 0.06 [, $mu [, $phi [, $sigma [, $systemconstant = 0.75 ]]]]]]]
	For new players, use the default values for rating, rd, and volatility.
	The systemconstant should be between 0.3 and 1.2, depending on system itself (this is game dependent, and must be set
		by estimation or experimentation)
		
Updating a Glicko2Player

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

This message and the following may not be removed or modified:

Caveat Emptor
	I make no assertions that either Glicko-2 or this code are correct.  Use at your own risk.

*******************************************************************************/

class Glicko2Player {
	public $rating;
	public $rd;
	public $sigma;

	public $mu;
	public $phi;
	public $tau;

	private $pi2 = 9.8696044;

	var $M = array();

	function __construct($rating = 1500, $rd = 350, $volatility = 0.06, $mu = null, $phi = null, $sigma = null, $systemconstant = 0.75) {
		// Step 1
		$this->rating = $rating;
		$this->rd = $rd;
		// volatility
		if (is_null($sigma)) {
			$this->sigma = $volatility;
		} else {
			$this->sigma = $sigma;
		}
		// System Constant
		$this->tau = $systemconstant;

		// Step 2
		// Rating
		if (is_null($mu)) {
			$this->mu = ( $this->rating - 1500 ) / 173.7178;
		} else {
			$this->mu = $mu;
		}
		// Rating Deviation
		if (is_null($phi)) {
			$this->phi = $this->rd / 173.7178;
		} else {
			$this->phi = $phi;
		}
	}

	function AddWin($OtherPlayer) {
		$this->M[] = $OtherPlayer->MatchElement(1);
	}

	function AddLoss($OtherPlayer) {
		$this->M[] = $OtherPlayer->MatchElement(0);
	}

	function AddDraw($OtherPlayer) {
		$this->M[] = $OtherPlayer->MatchElement(0.5);
	}

	function Update() {
		$Results = $this->AddMatches($this->M);
		$this->rating = $Results['r'];
		$this->rd = $Results['RD'];
		$this->mu = $Results['mu'];
		$this->phi = $Results['phi'];
		$this->sigma = $Results['sigma'];
		$this->M = array();
	}

	function MatchElement($score) {
		return array( 'mu' => $this->mu, 'phi' => $this->phi, 'score' => $score );
	}

	function AddMatches($M) {
		// This is where the Glicko2 rating calculation actually happens

		// Follow along the steps using: http://www.glicko.net/glicko/glicko2.pdf

		if (count($M) == 0) {
			$phi_p = sqrt( ( $this->phi * $this->phi ) + ( $this->sigma * $this->sigma ) );
			return array( 'r' => $this->rating, 'RD' => 173.7178 * $phi_p, 'mu' => $this->mu, 'phi' => $phi_p, 'sigma' => $this->sigma ) ;
		}

		// summation parts of Step 3 & 4 & 7
		$v_sum = 0;
		$delta_sum = 0;
		$mu_p_sum = 0;
		for ($j = 0; $j < count($M); $j++) {
			$E = $this->E( $this->mu, $M[$j]['mu'], $M[$j]['phi'] );
			$g = $this->g( $M[$j]['phi'] );
			$v_sum +=  ( $g * $g * $E * ( 1 - $E ) );

			$delta_sum += $g * ( $M[$j]['score'] - $E );

			$mu_p_sum += $g * ( $M[$j]['score'] - $E );
		}

		// Step 3
		// Estimated variance
		$v = 1.0 / $v_sum;

		// Step 4
		// Estimated improvment in rating
		$delta = $v * $delta_sum;

		// Step 5
		$a = log( $this->sigma * $this->sigma );
		$x_prev = $a;
		$x = $x_prev;
		$tausq = $this->tau * $this->tau;
		$phisq = $this->phi * $this->phi;
		$deltasq = $delta * $delta;
		do {
			$exp_xp = exp( $x_prev );
			$d = $this->phi * $this->phi + $v + $exp_xp;
			$deltadsq = $deltasq / ($d * $d);
			$h1 = -( $x_prev - $a ) / ( $tausq ) - ( 0.5 * $exp_xp / $d ) + ( 0.5 * $exp_xp * $deltadsq );
			$h2 = ( -1.0 / $tausq ) - ( ( 0.5 * $exp_xp ) * ( $phisq + $v ) / ( $d * $d ) ) + ( 0.5 * $deltasq * $exp_xp * ( $phisq + $v - $exp_xp ) / ( $d * $d * $d ) );
			$tmp_x = $x;
			$x = $x_prev - ( $h1 / $h2 );
			$x_prev = $tmp_x;
		} while (abs($x - $x_prev) > 0.1);

		$sigma_p = exp( $x / 2 );

		// Step 6
		$phi_star = sqrt( $phisq + ( $sigma_p * $sigma_p ) );

		// Step 7
		$phi_p = 1.0 / ( sqrt( ( 1.0 / ( $phi_star * $phi_star ) ) + ( 1.0 / $v ) ) );
		// New mu
		$mu_p = $this->mu + $phi_p * $phi_p * $mu_p_sum;

		return array( 'r' => ( 173.7178 * $mu_p ) + 1500, 'RD' => 173.7178 * $phi_p, 'mu' => $mu_p, 'phi' => $phi_p, 'sigma' => $sigma_p );
	}

	function g($phi) {
		return 1.0 / ( sqrt( 1.0 + ( 3.0 * $phi * $phi) / ( $this->pi2 ) ) );
	}

	function E($mu, $mu_j, $phi_j) {
		return 1.0 / ( 1.0 + exp( -$this->g($phi_j) * ( $mu - $mu_j ) ) );
	}
}
