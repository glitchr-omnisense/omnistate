<?php

namespace Omnistate\Exception;

use Omnisense\Contracts\Exception\ExceptionInterface;

/** Every failure of the family; omnisense/contracts' ExceptionInterface catches it too. */
class OmnistateException extends \RuntimeException implements ExceptionInterface
{
}
