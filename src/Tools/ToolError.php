<?php

namespace AssistantKit\Tools;

/**
 * A tool call the engine refused. The model gets the message back as the tool
 * result, so it can correct itself instead of stopping the answer.
 */
final class ToolError extends \RuntimeException {}
