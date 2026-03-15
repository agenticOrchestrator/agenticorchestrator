<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Exceptions;

use Exception;

/**
 * Thrown when a team attempts to access an agent they don't have permission for.
 */
class AgentAccessDeniedException extends Exception {}
