<?php

declare(strict_types=1);

/*
 * Creates the tables, loads the demo data and builds the search index.
 *   php bin/install.php           install if the database is empty
 *   php bin/install.php --fresh   drop everything and reinstall
 *   php bin/install.php --reindex rebuild the search index only
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

require __DIR__ . '/../src/bootstrap.php';

use App\App;
use App\Database;
use App\Installer;
use App\Search\Indexer;

$db = Database::connect(App::config('db'));
App::setDb($db);

if (in_array('--reindex', $argv, true)) {
    $stats = (new Indexer($db))->rebuild();
    printf("Index rebuilt: %d documents, %d terms, %d postings in %.2f s\n", $stats['documents'], $stats['terms'], $stats['postings'], $stats['seconds']);
    exit(0);
}

$fresh = in_array('--fresh', $argv, true);
if (!$fresh && Installer::isInstalled($db)) {
    echo "Already installed. Use --fresh to reinstall (this deletes all data) or --reindex.\n";
    exit(0);
}

$stats = Installer::install($db, $fresh);
printf(
    "Installed (%s): %d departments, %d users, %d documents.\nIndex: %d terms, %d postings (%.2f s).\n\nSign in with username 'manager' and password 'Password123!'.\n",
    App::config('db.driver'),
    $stats['departments'],
    $stats['users'],
    $stats['documents'],
    $stats['index']['terms'],
    $stats['index']['postings'],
    $stats['index']['seconds']
);
