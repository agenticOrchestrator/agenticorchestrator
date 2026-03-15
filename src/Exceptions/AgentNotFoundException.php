<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Exception;

/**
 * Thrown when attempting to resolve an agent that doesn't exist.
 */
class AgentNotFoundException extends Exception {}
