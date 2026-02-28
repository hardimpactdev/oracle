<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ProjectContext;

final class PromptBuilder
{
    /**
     * Build a plan prompt from a task description and project context.
     */
    public function buildPlanPrompt(string $taskDescription, ProjectContext $context, ?array $taskMeta = null): string
    {
        $sections = [];

        $sections[] = <<<'PROMPT'
You are an expert software architect. Analyze the following task and create a structured implementation plan.

Your output MUST be valid JSON with this exact schema:
{
  "steps": [
    {
      "title": "Short step title",
      "description": "Detailed description of what to do",
      "files": ["path/to/file1.php", "path/to/file2.php"],
      "verification": "How to verify this step is complete"
    }
  ],
  "summary": "One paragraph summary of the plan"
}
PROMPT;

        $sections[] = "## Task\n\n{$taskDescription}";

        if ($taskMeta !== null) {
            if (isset($taskMeta['completion_criteria']) && is_array($taskMeta['completion_criteria'])) {
                $criteria = implode("\n- ", $taskMeta['completion_criteria']);
                $sections[] = "## Completion Criteria\n\n- {$criteria}";
            }

            if (isset($taskMeta['complexity']) && is_string($taskMeta['complexity'])) {
                $sections[] = "## Complexity\n\n{$taskMeta['complexity']}";
            }

            if (isset($taskMeta['review_feedback']) && is_string($taskMeta['review_feedback'])) {
                $sections[] = "## Previous Review Feedback\n\nAddress the following feedback from a prior code review:\n\n{$taskMeta['review_feedback']}";
            }
        }

        $this->appendContext($sections, $context);

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Build a review prompt from a diff and project context.
     */
    public function buildReviewPrompt(string $diff, ProjectContext $context, ?string $taskDescription = null, ?array $internalProjects = null): string
    {
        $sections = [];

        $sections[] = <<<'PROMPT'
You are an expert code reviewer. Review the following changes against the project's conventions and best practices.

Your output MUST be valid JSON with this exact schema:
{
  "verdict": "approve" or "request_changes",
  "summary": "Brief summary of the review",
  "comments": [
    {
      "path": "relative/file/path.php",
      "line": 42,
      "body": "Review comment explaining the issue"
    }
  ],
  "workarounds": [
    {
      "project": "project-name or null",
      "description": "Description of the workaround/friction point",
      "severity": "low|medium|high"
    }
  ]
}

Review criteria:
- Code follows project conventions (from AGENTS.md/CLAUDE.md)
- No security vulnerabilities (OWASP top 10)
- No unnecessary complexity or over-engineering
- Tests are included for new functionality
- No debug code, dead code, or commented-out code
PROMPT;

        if ($taskDescription !== null) {
            $sections[] = "## Task Description\n\n{$taskDescription}";
        }

        if ($internalProjects !== null && $internalProjects !== []) {
            $projectList = implode(', ', $internalProjects);
            $sections[] = "## Internal Projects\n\nThese are internal projects. If the diff contains workarounds for limitations in these projects, flag them as friction points:\n\n{$projectList}";
        }

        $sections[] = "## Diff\n\n```diff\n{$diff}\n```";

        $this->appendContext($sections, $context);

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Build an ask prompt from a question and project context.
     */
    public function buildAskPrompt(string $question, ProjectContext $context): string
    {
        $sections = [];

        $sections[] = <<<'PROMPT'
You are a knowledgeable software engineer. Answer the following question about this project accurately and concisely.

Use the provided project context to inform your answer. If you're unsure, say so rather than guessing.

Format your answer in clear markdown.
PROMPT;

        $sections[] = "## Question\n\n{$question}";

        $this->appendContext($sections, $context);

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Build a learn prompt to extract learnings from a PR or review.
     */
    public function buildLearnPrompt(string $content, string $source, ProjectContext $context): string
    {
        $sections = [];

        $sections[] = <<<'PROMPT'
You are an expert at extracting reusable knowledge from code changes and reviews.

Analyze the following content and extract discrete, actionable learnings.

Your output MUST be valid JSON with this exact schema:
{
  "learnings": [
    {
      "content": "The specific learning or pattern discovered",
      "category": "pattern|gotcha|convention|solution",
      "importance": 0.5
    }
  ]
}

Categories:
- pattern: A reusable code pattern or approach
- gotcha: A non-obvious pitfall or edge case
- convention: A project-specific convention or rule
- solution: A solution to a specific problem
PROMPT;

        $sections[] = "## Source\n\n{$source}";
        $sections[] = "## Content\n\n{$content}";

        $this->appendContext($sections, $context);

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Build a verification prompt to evaluate coder work against task requirements.
     */
    public function buildVerifyPrompt(
        string $transcript,
        string $taskDescription,
        string $beadsStatus,
        ProjectContext $context,
        ?string $solutionsIndex = null,
        ?array $taskMeta = null,
    ): string {
        $sections = [];

        $sections[] = <<<'PROMPT'
You are an expert verification agent. Evaluate whether the coder's work satisfies the task requirements.

Your output MUST be valid JSON with this exact schema:
{
  "verdict": "pass|follow_up|package_issue|fail",
  "summary": "Brief summary of the verification result",
  "follow_up_question": "Specific question for the coder (only when verdict is follow_up)",
  "package_issues": [
    {
      "package": "package-name",
      "description": "Description of the package gap",
      "severity": "low|medium|high",
      "blocking": false
    }
  ],
  "confidence": 0.85
}

Verdict criteria:
- "pass": All beads completed, implementation matches task requirements and completion criteria, no quality issues.
- "follow_up": Unclear whether requirements are fully met. You need a specific answer from the coder before deciding. Include a clear, actionable follow_up_question.
- "package_issue": Implementation is acceptable but a managed/internal package has a gap or limitation that should be tracked. Include the package_issues array. The work itself passes.
- "fail": Fundamentally wrong approach, critical requirements missed, or quality issues that cannot be fixed with follow-ups. Use sparingly — only when the work is clearly inadequate.

Confidence: A value between 0.0 and 1.0 indicating how certain you are in the verdict. High confidence (>0.8) means clear evidence supports the verdict. Low confidence (<0.5) means evidence is ambiguous.
PROMPT;

        $sections[] = "## Task Description\n\n{$taskDescription}";

        if ($taskMeta !== null) {
            if (isset($taskMeta['completion_criteria']) && is_array($taskMeta['completion_criteria'])) {
                $criteria = implode("\n- ", $taskMeta['completion_criteria']);
                $sections[] = "## Completion Criteria\n\n- {$criteria}";
            }

            if (isset($taskMeta['complexity']) && is_string($taskMeta['complexity'])) {
                $sections[] = "## Complexity\n\n{$taskMeta['complexity']}";
            }

            if (isset($taskMeta['previous_review_feedback']) && is_string($taskMeta['previous_review_feedback'])) {
                $sections[] = "## Previous Review Feedback\n\nThe coder was asked to address the following feedback:\n\n{$taskMeta['previous_review_feedback']}";
            }
        }

        $sections[] = "## Beads Status\n\n```json\n{$beadsStatus}\n```";

        $sections[] = "## Session Transcript\n\n{$transcript}";

        if ($solutionsIndex !== null) {
            $sections[] = "## Solutions Index\n\nKnown solution patterns the coder should follow:\n\n{$solutionsIndex}";
        }

        $this->appendContext($sections, $context);

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Append project context sections to the prompt.
     *
     * @param  array<string>  $sections
     */
    private function appendContext(array &$sections, ProjectContext $context): void
    {
        if ($context->conventions !== null) {
            $sections[] = "## Project Conventions\n\n{$context->conventions}";
        }

        if ($context->memories !== []) {
            $memoryText = implode("\n\n---\n\n", $context->memoryContents());
            $sections[] = "## Relevant Learnings from Memory\n\n{$memoryText}";
        }

        if ($context->docs !== []) {
            $docList = implode("\n- ", $context->docPaths());
            $sections[] = "## Available Documentation\n\nThe following solution docs are available:\n- {$docList}";
        }
    }
}
