<?php
declare(strict_types=1);

/** Сюда попадают все запросы к /api/ без конкретного файла. */
http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
echo 'Forbidden';
