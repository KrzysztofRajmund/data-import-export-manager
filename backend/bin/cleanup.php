<?php

declare(strict_types=1);

$baseDirectory = dirname(__DIR__);
$days = 7;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--days=')) {
        $days = max(0, (int) substr($argument, 7));
    }
}

$cutoff = time() - ($days * 86400);
$deleted = 0;

foreach ([$baseDirectory . '/storage/imports', $baseDirectory . '/storage/exports'] as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $items = new DirectoryIterator($directory);
    foreach ($items as $item) {
        if ($item->isDot() || $item->isDir()) {
            continue;
        }
        if ($item->getMTime() < $cutoff && @unlink($item->getPathname())) {
            $deleted++;
        }
    }
}

fwrite(STDOUT, sprintf("Deleted %d file(s) older than %d day(s).\n", $deleted, $days));
