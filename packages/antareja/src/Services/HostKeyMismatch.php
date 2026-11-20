<?php

namespace Antareja\Services;

/** Host key SSH perangkat berbeda dari yang tersimpan (kemungkinan perangkat diganti atau serangan man-in-the-middle). */
class HostKeyMismatch extends \RuntimeException
{
}
