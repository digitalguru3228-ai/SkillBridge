<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
$p = db();
echo "DB OK\n";
$cols = $p->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='industry_hub' AND table_name='candidate_skills' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_COLUMN);
echo "candidate_skills: " . implode(', ', $cols) . "\n";
$idx = $p->query("SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema='industry_hub' AND table_name='candidate_skills' AND index_name='uk_candidate_skill' LIMIT 1")->fetchColumn();
echo "uk_candidate_skill: " . ($idx ?: 'MISSING') . "\n";
