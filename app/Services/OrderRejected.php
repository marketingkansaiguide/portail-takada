<?php

namespace App\Services;

/** Commande refusée : rien n'a été enregistré (la transaction est annulée). */
final class OrderRejected extends \RuntimeException {}
