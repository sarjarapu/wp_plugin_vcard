<?php

/**
 * PHP-based Changelog Generator
 * Generates changelog from conventional commits
 */

class ChangelogGenerator
{
    private string $changelogFile;
    private string $gitDir;

    public function __construct(string $changelogFile = 'CHANGELOG.md', string $gitDir = '.')
    {
        $this->changelogFile = $changelogFile;
        $this->gitDir = $gitDir;
    }

    public function generate(?string $version = null): void
    {
        $commits = $this->getConventionalCommits($version);
        $changelog = $this->formatChangelog($commits, $version);

        if ($version) {
            $this->prependToChangelog($changelog);
        } else {
            echo $changelog;
        }
    }

    private function getConventionalCommits(?string $version = null): array
    {
        $range = $version ? "v{$version}..HEAD" : "HEAD~10..HEAD";
        $command = "git log --pretty=format:'%H|%s|%b' {$range}";

        $output = shell_exec($command);
        if (!$output) {
            return [];
        }

        $commits = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode('|', $line, 3);
            if (count($parts) < 2) {
                continue;
            }

            $hash = $parts[0];
            $subject = $parts[1];
            $body = $parts[2] ?? '';

            // Parse conventional commit
            if (preg_match('/^(\w+)(\(.+\))?(!)?:\s*(.+)$/', $subject, $matches)) {
                $type = $matches[1];
                $scope = isset($matches[2]) ? trim($matches[2], '()') : '';
                $breaking = !empty($matches[3]);
                $description = $matches[4];

                $commits[] = [
                    'hash' => $hash,
                    'type' => $type,
                    'scope' => $scope,
                    'breaking' => $breaking,
                    'description' => $description,
                    'body' => $body
                ];
            }
        }

        return $commits;
    }

    private function formatChangelog(array $commits, ?string $version = null): string
    {
        if (empty($commits)) {
            return ""; // Don't add anything if no commits
        }

        $date = date('Y-m-d');
        $header = $version ? "## [{$version}] - {$date}\n\n" : "## Unreleased\n\n";

        $sections = [
            'feat' => '### Features',
            'fix' => '### Bug Fixes',
            'perf' => '### Performance Improvements',
            'refactor' => '### Code Refactoring',
            'docs' => '### Documentation',
            'style' => '### Code Style',
            'test' => '### Tests',
            'chore' => '### Chores',
            'ci' => '### CI/CD',
            'build' => '### Build System'
        ];

        $breakingChanges = [];
        $groupedCommits = [];

        foreach ($commits as $commit) {
            if ($commit['breaking']) {
                $breakingChanges[] = $commit;
            }

            $type = $commit['type'];
            if (!isset($groupedCommits[$type])) {
                $groupedCommits[$type] = [];
            }
            $groupedCommits[$type][] = $commit;
        }

        $output = $header;

        // Breaking changes first
        if (!empty($breakingChanges)) {
            $output .= "### ⚠️ BREAKING CHANGES\n\n";
            foreach ($breakingChanges as $commit) {
                $output .= "- **{$commit['scope']}**: {$commit['description']}\n";
                if (!empty($commit['body'])) {
                    $output .= "  - {$commit['body']}\n";
                }
            }
            $output .= "\n";
        }

        // Regular changes
        foreach ($sections as $type => $title) {
            if (isset($groupedCommits[$type]) && !empty($groupedCommits[$type])) {
                $output .= "{$title}\n\n";
                foreach ($groupedCommits[$type] as $commit) {
                    $scope = $commit['scope'] ? "**{$commit['scope']}**: " : '';
                    $output .= "- {$scope}{$commit['description']}\n";
                }
                $output .= "\n";
            }
        }

        return $output;
    }

    private function prependToChangelog(string $newContent): void
    {
        // Don't update if no content
        if (empty(trim($newContent))) {
            echo "ℹ️  No changes to add to changelog\n";
            return;
        }

        $existingContent = '';
        if (file_exists($this->changelogFile)) {
            $existingContent = file_get_contents($this->changelogFile);
        }

        // Find the end of the header section (after the description line)
        $lines = explode("\n", $existingContent);
        $insertIndex = 0;
        
        // Look for the end of the header (after "See [standard-version]...")
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], 'See [standard-version]') !== false) {
                $insertIndex = $i + 1;
                break;
            }
        }
        
        // Insert the new content after the header
        array_splice($lines, $insertIndex, 0, ['', $newContent]);
        
        $fullContent = implode("\n", $lines);
        file_put_contents($this->changelogFile, $fullContent);

        echo "✅ Changelog updated: {$this->changelogFile}\n";
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $version = $argv[1] ?? null;
    $generator = new ChangelogGenerator();
    $generator->generate($version);
}
