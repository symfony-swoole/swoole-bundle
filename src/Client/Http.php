<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Client;

enum Http: string
{
    case METHOD_GET = 'GET';
    case METHOD_HEAD = 'HEAD';
    case METHOD_POST = 'POST';
    case METHOD_DELETE = 'DELETE';
    case METHOD_PATCH = 'PATCH';
    case METHOD_TRACE = 'TRACE';
    case METHOD_OPTIONS = 'OPTIONS';

    case HEADER_CONTENT_TYPE = 'content-type';
    case HEADER_ACCEPT = 'accept';

    case CONTENT_TYPE_APPLICATION_JSON = 'application/json';
    case CONTENT_TYPE_TEXT_PLAIN = 'text/plain';
    case CONTENT_TYPE_TEXT_HTML = 'text/html';
}
