<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

function aiAdvisorScore(string $root, array $signals): array
{
	$counts = is_array($signals['counts'] ?? null) ? $signals['counts'] : [];
	$aiSurface = is_array($signals['ai_surface'] ?? null) ? $signals['ai_surface'] : [];
	$toolchain = is_array($signals['toolchain'] ?? null) ? $signals['toolchain'] : [];
	$trackedFiles = (int) ($signals['tracked_files_count'] ?? 0);

	$aiSurfaceTotal = count($aiSurface);
	$aiSurfacePresent = 0;
	foreach ($aiSurface as $present) {
		if ($present) {
			$aiSurfacePresent++;
		}
	}
	$aiSurfaceCoverage = $aiSurfaceTotal > 0
		? (int) round(($aiSurfacePresent * 100) / $aiSurfaceTotal)
		: 0;

	$testsPhp = (int) ($counts['tests_php'] ?? 0);
	$testsShell = (int) ($counts['tests_shell'] ?? 0);
	$testReadiness = min(100, ($testsPhp * 2) + ($testsShell * 8));

	$scriptsAi = (int) ($counts['scripts_ai'] ?? 0);
	$scriptsCopilot = (int) ($counts['scripts_copilot'] ?? 0);
	$toolsAiPhp = (int) ($counts['tools_ai_php'] ?? 0);
	$scriptSafety = 0;
	if ($scriptsAi > 0) {
		$scriptSafety += 70;
	}
	if ($toolsAiPhp > 0) {
		$scriptSafety += 20;
	}
	if ($scriptsAi > 0 && $scriptsCopilot === $scriptsAi) {
		$scriptSafety += 10;
	}
	$scriptSafety = min(100, $scriptSafety);

	$toolchainTotal = count($toolchain);
	$toolchainPresent = 0;
	foreach ($toolchain as $present) {
		if ($present) {
			$toolchainPresent++;
		}
	}
	$toolchainReadiness = $toolchainTotal > 0
		? (int) round(($toolchainPresent * 100) / $toolchainTotal)
		: 0;

	$complexityRisk = 0;
	if ($trackedFiles > 1200) {
		$complexityRisk = min(100, (int) round(($trackedFiles - 1200) / 8));
	}

	$generatedArtifacts = [
		'project-signals.json',
		'project-signals.md',
		'project-scorecard.json',
		'advisor-secret-findings.json',
		'advisor-token-budget.json',
		'advisor-context.md',
		'advisor-prompt.md',
	];
	$generatedDir = aiAdvisorGeneratedDir($root);
	$presentArtifacts = 0;
	foreach ($generatedArtifacts as $name) {
		$path = $generatedDir . DIRECTORY_SEPARATOR . $name;
		if (is_file($path)) {
			$presentArtifacts++;
		}
	}
	$generatedDocHygiene = 50 + (int) round(($presentArtifacts * 50) / count($generatedArtifacts));

	$scores = [
		'ai_surface_coverage' => $aiSurfaceCoverage,
		'test_readiness' => $testReadiness,
		'script_safety' => $scriptSafety,
		'toolchain_readiness' => $toolchainReadiness,
		'complexity_risk' => $complexityRisk,
		'generated_doc_hygiene' => $generatedDocHygiene,
	];

	$overall = (int) round(array_sum($scores) / max(1, count($scores)));

	$scorecard = [
		'schema_version' => 1,
		'scores' => $scores,
		'overall' => $overall,
	];

	aiAdvisorWriteJson($generatedDir . DIRECTORY_SEPARATOR . 'project-scorecard.json', $scorecard);

	$md = "# Project Scorecard\n\n";
	$md .= "- overall score: `{$overall}`\n\n";
	$md .= "| Area | Score |\n";
	$md .= "|---|---:|\n";
	foreach ($scores as $area => $value) {
		$md .= "| {$area} | {$value} |\n";
	}
	aiAdvisorWriteMarkdown($generatedDir . DIRECTORY_SEPARATOR . 'project-scorecard.md', $md);

	return $scorecard;
}
