<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/notifications.php';
requireRole('industry');

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function json_for_js(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
function flash(string $type, string $message): void {
    $_SESSION['industry_flash'] = ['type' => $type, 'message' => $message];
}
function normalize_skills(string $raw): array {
    $parts = preg_split('/[,\n]+/', $raw) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $name = trim($part);
        if ($name !== '' && !in_array(mb_strtolower($name), array_map('mb_strtolower', $out), true)) {
            $out[] = $name;
        }
    }
    return $out;
}
function get_or_create_skill(PDO $pdo, string $name): int {
    $stmt = $pdo->prepare('SELECT id FROM skills WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $insert = $pdo->prepare('INSERT INTO skills (name, category, demand_level) VALUES (?, ?, ?)');
    $insert->execute([$name, 'Industry', 'Medium']);
    return (int)$pdo->lastInsertId();
}
function sync_opportunity_skills(PDO $pdo, int $opportunityId, array $skillNames): void {
    $pdo->prepare('DELETE FROM opportunity_skills WHERE opportunity_id = ?')->execute([$opportunityId]);
    $insert = $pdo->prepare('INSERT INTO opportunity_skills (opportunity_id, skill_id, importance) VALUES (?, ?, ?)');
    foreach ($skillNames as $name) {
        $skillId = get_or_create_skill($pdo, $name);
        $insert->execute([$opportunityId, $skillId, 'Required']);
    }
}

$pdo = db();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';
    // CSRF verification on all POST actions
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        flash('error', 'Invalid security token. Please refresh and try again.');
        header('Location: index.php'); exit;
    }
    try {
        if ($action === 'save_opportunity') {
            $id = (int)($_POST['id'] ?? 0);
            $type = ($_POST['type'] ?? '') === 'Internship' ? 'Internship' : 'Full-time Job';
            $title = trim((string)($_POST['title'] ?? ''));
            $department = trim((string)($_POST['department'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $workMode = trim((string)($_POST['work_mode'] ?? ''));
            $skills = normalize_skills((string)($_POST['required_skills'] ?? ''));
            $openings = (int)($_POST['openings'] ?? 0);
            $experience = trim((string)($_POST['experience'] ?? ''));
            $qualification = trim((string)($_POST['qualification'] ?? ''));
            $minCgpaRaw = trim((string)($_POST['min_cgpa'] ?? ''));
            $minCgpa = $minCgpaRaw === '' ? 0.00 : (float)$minCgpaRaw;
            $allowedBranches = trim((string)($_POST['allowed_branches'] ?? ''));
            $salary = trim((string)($_POST['salary'] ?? ''));
            $stipend = trim((string)($_POST['stipend'] ?? ''));
            $duration = trim((string)($_POST['duration'] ?? ''));
            $eligibility = trim((string)($_POST['eligibility'] ?? ''));
            $deadline = trim((string)($_POST['deadline'] ?? '')) ?: null;
            $learningOutcomes = trim((string)($_POST['learning_outcomes'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status = in_array($_POST['status'] ?? '', ['Draft','Active','Closed'], true) ? $_POST['status'] : 'Draft';
            $assessmentId = (int)($_POST['assessment_id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);

            if (!$companyId) throw new RuntimeException('Industry account is not linked to a company.');
            if ($title === '') throw new RuntimeException('Job / internship title is required.');
            if ($department === '') throw new RuntimeException('Department / function is required.');
            if ($location === '') throw new RuntimeException('Location is required.');
            if (!in_array($workMode, ['In-office','Remote','Hybrid'], true)) throw new RuntimeException('Please select a valid work mode.');
            if ($openings < 1 || $openings > 10000) throw new RuntimeException('Openings must be between 1 and 10,000.');
            if ($qualification === '') throw new RuntimeException('Minimum qualification is required.');
            if ($minCgpa < 0 || $minCgpa > 10) throw new RuntimeException('Minimum CGPA must be between 0 and 10.');
            if (!$skills) throw new RuntimeException('Add at least one required skill.');
            if ($description === '') throw new RuntimeException('Role description is required.');
            if ($type === 'Full-time Job') {
                if ($experience === '') throw new RuntimeException('Experience requirement is required for a job.');
                if ($salary === '') throw new RuntimeException('Salary / CTC information is required for a job.');
            } else {
                if ($duration === '') throw new RuntimeException('Internship duration is required.');
                if ($stipend === '') throw new RuntimeException('Stipend information is required for an internship.');
                if ($eligibility === '') throw new RuntimeException('Internship eligibility is required.');
            }
            if ($status === 'Active' && !$deadline) throw new RuntimeException('An application deadline is required before publishing an opportunity.');
            if ($status === 'Active' && $deadline && $deadline < date('Y-m-d')) throw new RuntimeException('The application deadline cannot be in the past for an active opportunity.');
            if ($deadline && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) !== 1) throw new RuntimeException('Please provide a valid deadline.');

            if ($assessmentId > 0) {
                $check = $pdo->prepare('SELECT id FROM industry_assessments WHERE id=? AND company_id=? LIMIT 1');
                $check->execute([$assessmentId, $companyId]);
                if (!$check->fetchColumn()) throw new RuntimeException('Selected pre-placement assessment was not found for this company.');
            }

            $pdo->beginTransaction();
            try {
                $values = [
                    $title, $type, $department, $location, $workMode, implode(', ', $skills), $openings,
                    $experience, $qualification, $minCgpa, $allowedBranches, $salary, $stipend, $duration,
                    $eligibility, $deadline, $status, $description, $learningOutcomes, $assessmentId ?: null
                ];
                if ($id > 0) {
                    $ownership = $pdo->prepare('SELECT id, assessment_id FROM opportunities WHERE id=? AND company_id=? LIMIT 1');
                    $ownership->execute([$id, $companyId]);
                    $existing = $ownership->fetch();
                    if (!$existing) throw new RuntimeException('Opportunity not found or you do not have access to it.');
                    $stmt = $pdo->prepare('UPDATE opportunities SET title=?, type=?, department=?, location=?, work_mode=?, required_skills=?, openings=?, experience=?, qualification=?, min_cgpa=?, allowed_branches=?, salary=?, stipend=?, duration=?, eligibility=?, deadline=?, status=?, description=?, learning_outcomes=?, assessment_id=? WHERE id=? AND company_id=?');
                    $stmt->execute([...$values, $id, $companyId]);
                    $savedId = $id;
                } else {
                    $stmt = $pdo->prepare('INSERT INTO opportunities (title,type,department,location,work_mode,required_skills,openings,experience,qualification,min_cgpa,allowed_branches,salary,stipend,duration,eligibility,deadline,status,description,learning_outcomes,assessment_id,company_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([...$values, $companyId]);
                    $savedId = (int)$pdo->lastInsertId();
                }
                sync_opportunity_skills($pdo, $savedId, $skills);
                $pdo->prepare('UPDATE industry_assessments SET opportunity_id=NULL WHERE opportunity_id=? AND company_id=?')->execute([$savedId, $companyId]);
                if ($assessmentId > 0) {
                    $pdo->prepare('UPDATE industry_assessments SET opportunity_id=? WHERE id=? AND company_id=?')->execute([$savedId, $assessmentId, $companyId]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            flash('success', $id > 0 ? 'Opportunity updated successfully.' : 'Opportunity posted successfully.');
            header('Location: index.php#' . ($type === 'Internship' ? 'internships' : 'jobs'));
            exit;
        }
        if ($action === 'delete_opportunity') {
            $id = (int)($_POST['id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            if (!$companyId) throw new RuntimeException('Industry account is not linked to a company.');
            $count = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE opportunity_id=?');
            $count->execute([$id]);
            if ((int)$count->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE opportunities SET status='Closed' WHERE id=? AND company_id=?");
                $stmt->execute([$id,$companyId]);
                flash('success','This opportunity has applications, so it was safely closed instead of deleted.');
            } else {
                $stmt = $pdo->prepare('DELETE FROM opportunities WHERE id=? AND company_id=?');
                $stmt->execute([$id,$companyId]);
                flash('success','Opportunity deleted successfully.');
            }
            header('Location: index.php#jobs'); exit;
        }
        if ($action === 'save_collaboration') {
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            if (!$companyId) throw new RuntimeException('Industry account is not linked to a company.');
            $stmt = $pdo->prepare('INSERT INTO collaboration_requests (company_id,institute,collaboration_type,focus_skills,message,event_date,status) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$companyId,trim((string)$_POST['institute']),trim((string)$_POST['type']),trim((string)$_POST['focus_skills']),trim((string)$_POST['message']),($_POST['event_date'] ?? '') ?: null,'Pending']);
            flash('success','Collaboration request sent.'); header('Location: index.php#collaboration'); exit;
        }
        if ($action === 'save_learning_program') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $type = in_array($_POST['type'] ?? '', ['Training','Certification','Workshop','Mentorship'], true) ? $_POST['type'] : 'Training';
            $skills = implode(', ', normalize_skills((string)($_POST['target_skills'] ?? '')));
            $duration = trim((string)($_POST['duration'] ?? ''));
            $stipend = trim((string)($_POST['stipend_or_fee'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $status = in_array($_POST['status'] ?? '', ['Draft','Active','Closed'], true) ? $_POST['status'] : 'Draft';
            $description = trim((string)($_POST['description'] ?? ''));
            $moduleTitlesRaw = $_POST['modules'] ?? [];
            $moduleDescriptionsRaw = $_POST['module_descriptions'] ?? [];
            $moduleTitles = is_array($moduleTitlesRaw) ? $moduleTitlesRaw : [];
            $moduleDescriptions = is_array($moduleDescriptionsRaw) ? $moduleDescriptionsRaw : [];
            $modules = [];
            foreach ($moduleTitles as $idx => $moduleTitle) {
                $moduleTitle = trim((string)$moduleTitle);
                if ($moduleTitle === '') continue;
                $modules[] = [
                    'title' => $moduleTitle,
                    'description' => trim((string)($moduleDescriptions[$idx] ?? '')),
                    'order' => count($modules) + 1
                ];
            }
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            if (!$companyId) throw new RuntimeException('Industry account is not linked to a company.');
            if ($title === '') throw new RuntimeException('Program title is required.');
            if (!$skills) throw new RuntimeException('Add at least one target skill.');
            if ($duration === '') throw new RuntimeException('Program duration is required.');
            if ($location === '') throw new RuntimeException('Program location / mode is required.');
            if ($description === '') throw new RuntimeException('Program description is required.');
            if ($status === 'Active' && count($modules) === 0) throw new RuntimeException('Add at least one module before publishing an active program.');
            if (count($modules) > 30) throw new RuntimeException('A learning program can contain at most 30 modules.');

            $pdo->beginTransaction();
            try {
                if ($id > 0) {
                    $ownership = $pdo->prepare('SELECT id FROM learning_programs WHERE id=? AND company_id=? LIMIT 1');
                    $ownership->execute([$id, $companyId]);
                    if (!$ownership->fetchColumn()) throw new RuntimeException('Learning program not found or you do not have access to it.');
                    $enrolled = $pdo->prepare("SELECT COUNT(*) FROM learning_enrollments WHERE program_id=? AND status <> 'Withdrawn'");
                    $enrolled->execute([$id]);
                    $hasEnrollments = (int)$enrolled->fetchColumn() > 0;
                    $stmt = $pdo->prepare('UPDATE learning_programs SET title=?, type=?, target_skills=?, duration=?, stipend_or_fee=?, location=?, description=?, status=? WHERE id=? AND company_id=?');
                    $stmt->execute([$title,$type,$skills,$duration,$stipend,$location,$description,$status,$id,$companyId]);
                    if (!$hasEnrollments) {
                        $pdo->prepare('DELETE FROM learning_program_modules WHERE program_id=?')->execute([$id]);
                        $moduleStmt = $pdo->prepare('INSERT INTO learning_program_modules (program_id,module_title,description,order_num) VALUES (?,?,?,?)');
                        foreach ($modules as $m) $moduleStmt->execute([$id,$m['title'],$m['description'],$m['order']]);
                    } elseif (count($modules) > 0) {
                        $existingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM learning_program_modules WHERE program_id=?');
                        $existingCountStmt->execute([$id]);
                        if ((int)$existingCountStmt->fetchColumn() === 0) throw new RuntimeException('This program already has students enrolled, so its module structure cannot be changed safely.');
                    }
                    flash('success','Learning program updated successfully.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO learning_programs (company_id,title,type,target_skills,duration,stipend_or_fee,location,description,status) VALUES (?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$companyId,$title,$type,$skills,$duration,$stipend,$location,$description,$status]);
                    $programId = (int)$pdo->lastInsertId();
                    $moduleStmt = $pdo->prepare('INSERT INTO learning_program_modules (program_id,module_title,description,order_num) VALUES (?,?,?,?)');
                    foreach ($modules as $m) $moduleStmt->execute([$programId,$m['title'],$m['description'],$m['order']]);
                    flash('success','Learning program published successfully.');
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            header('Location: index.php#learning'); exit;
        }
        if ($action === 'delete_learning_program') {
            $id = (int)($_POST['id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            if (!$companyId || $id <= 0) throw new RuntimeException('Invalid learning program.');
            $chk = $pdo->prepare('SELECT COUNT(*) FROM learning_enrollments WHERE program_id=?');
            $chk->execute([$id]);
            if ((int)$chk->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE learning_programs SET status='Closed' WHERE id=? AND company_id=?");
                $stmt->execute([$id,$companyId]);
                flash('success','This program has enrollment history, so it was safely closed instead of deleted.');
            } else {
                $stmt = $pdo->prepare('DELETE FROM learning_programs WHERE id=? AND company_id=?');
                $stmt->execute([$id,$companyId]);
                flash('success','Learning program deleted successfully.');
            }
            header('Location: index.php#learning'); exit;
        }
        if ($action === 'update_application_status') {
            $appId = (int)($_POST['application_id'] ?? 0);
            $newStatus = trim((string)($_POST['status'] ?? ''));
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            if (in_array($newStatus, ['Applied','Under Review','Shortlisted','Interview','Selected','Rejected','Withdrawn'], true)) {
                $stmt = $pdo->prepare("UPDATE applications a JOIN opportunities o ON o.id = a.opportunity_id SET a.status = ?, a.updated_at = CURRENT_TIMESTAMP WHERE a.id = ? AND o.company_id = ?");
                $stmt->execute([$newStatus, $appId, $companyId]);
                if ($stmt->rowCount() > 0) {
                    $appInfo = $pdo->prepare('SELECT a.candidate_id,o.title,c.name company_name FROM applications a JOIN opportunities o ON o.id=a.opportunity_id JOIN companies c ON c.id=o.company_id WHERE a.id=? AND o.company_id=? LIMIT 1');
                    $appInfo->execute([$appId,$companyId]);
                    $appRow=$appInfo->fetch();
                    if ($appRow) {
                        sbNotifyCandidate($pdo,(int)$appRow['candidate_id'],'Application status updated','Your application for “'.$appRow['title'].'” at '.$appRow['company_name'].' is now marked as “'.$newStatus.'”.','application','application',$appId);
                    }
                }
                flash('success', 'Application status updated to ' . $newStatus);
            }
            header('Location: index.php#applications'); exit;
        }
        if ($action === 'save_industry_resource') {
            $title = trim((string)($_POST['title'] ?? ''));
            $topic = trim((string)($_POST['topic'] ?? 'General'));
            $audience = in_array($_POST['audience'] ?? '', ['Faculty','Institute','Both'], true) ? $_POST['audience'] : 'Both';
            $description = trim((string)($_POST['description'] ?? ''));
            $url = trim((string)($_POST['resource_url'] ?? ''));
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            if ($title === '') throw new RuntimeException('Resource title is required.');
            $stmt = $pdo->prepare("INSERT INTO industry_resources (company_id, title, topic, audience, description, resource_url) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$companyId, $title, $topic, $audience, $description, $url]);
            flash('success', 'Industry resource published for institutes.');
            header('Location: index.php#resources'); exit;
        }
        if ($action === 'delete_industry_resource') {
            $id = (int)($_POST['id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM industry_resources WHERE id = ? AND company_id = ?");
            $stmt->execute([$id, $companyId]);
            flash('success', 'Resource deleted.');
            header('Location: index.php#resources'); exit;
        }
        if ($action === 'save_collaboration_feedback') {
            $collabId = (int)($_POST['collaboration_id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            $ownCheck = $pdo->prepare('SELECT id FROM collaboration_requests WHERE id = ? AND company_id = ? LIMIT 1');
            $ownCheck->execute([$collabId, $companyId]);
            if (!$ownCheck->fetchColumn()) {
                throw new RuntimeException('Collaboration request not found for your company.');
            }
            $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
            $notes = trim((string)($_POST['outcome_notes'] ?? ''));
            $text = trim((string)($_POST['feedback_text'] ?? ''));
            $stmt = $pdo->prepare("INSERT INTO collaboration_feedback (collaboration_id, submitter_type, rating, outcome_notes, feedback_text) VALUES (?, 'Industry', ?, ?, ?)");
            $stmt->execute([$collabId, $rating, $notes, $text]);
            flash('success', 'Collaboration feedback submitted.');
            header('Location: index.php#collaboration'); exit;
        }
        if ($action === 'save_assessment') {
            $id = (int)($_POST['id'] ?? 0);
            $oppId = (int)($_POST['opportunity_id'] ?? 0) ?: null;
            $title = trim((string)($_POST['title'] ?? ''));
            $duration = max(5, (int)($_POST['duration_mins'] ?? 30));
            $passScore = (float)($_POST['passing_score_pct'] ?? 60.0);
            $description = trim((string)($_POST['description'] ?? ''));
            $status = in_array($_POST['status'] ?? '', ['Draft','Active','Closed'], true) ? $_POST['status'] : 'Active';
            if ($title === '') throw new RuntimeException('Assessment title is required.');
            $companyId = (int)($_SESSION['company_id'] ?? 0);

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE industry_assessments SET opportunity_id=?, title=?, duration_mins=?, passing_score_pct=?, description=?, status=? WHERE id=? AND company_id=?");
                $stmt->execute([$oppId, $title, $duration, $passScore, $description, $status, $id, $companyId]);
                flash('success', 'Assessment updated.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO industry_assessments (company_id, opportunity_id, title, duration_mins, passing_score_pct, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$companyId, $oppId, $title, $duration, $passScore, $description, $status]);
                $id = (int)$pdo->lastInsertId();
                if ($oppId > 0) {
                    $pdo->prepare("UPDATE opportunities SET assessment_id=? WHERE id=? AND company_id=?")->execute([$id, $oppId, $companyId]);
                }
                flash('success', 'Pre-Placement Assessment created successfully.');
            }
            header('Location: index.php#assessments'); exit;
        }
        if ($action === 'add_assessment_question') {
            $assId = (int)($_POST['assessment_id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            $ownCheck = $pdo->prepare('SELECT id FROM industry_assessments WHERE id = ? AND company_id = ? LIMIT 1');
            $ownCheck->execute([$assId, $companyId]);
            if (!$ownCheck->fetchColumn()) {
                throw new RuntimeException('Assessment not found for your company.');
            }
            $question = trim((string)($_POST['question'] ?? ''));
            $optA = trim((string)($_POST['option_a'] ?? ''));
            $optB = trim((string)($_POST['option_b'] ?? ''));
            $optC = trim((string)($_POST['option_c'] ?? ''));
            $optD = trim((string)($_POST['option_d'] ?? ''));
            $correct = $_POST['correct_option'] ?? 'A';
            $marks = max(1, (int)($_POST['marks'] ?? 1));
            $difficulty = in_array($_POST['difficulty'] ?? '', ['Easy','Medium','Hard'], true) ? $_POST['difficulty'] : 'Medium';
            $topic = trim((string)($_POST['skill_topic'] ?? ''));

            if ($assId <= 0 || $question === '' || $optA === '' || $optB === '') throw new RuntimeException('Question text and options are required.');
            $pdo->prepare("INSERT INTO industry_assessment_questions (assessment_id, question, option_a, option_b, option_c, option_d, correct_option, marks, difficulty, skill_topic) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$assId, $question, $optA, $optB, $optC, $optD, $correct, $marks, $difficulty, $topic]);
            flash('success', 'Question added to assessment.');
            header('Location: index.php#assessments'); exit;
        }
        if ($action === 'delete_assessment_question') {
            $qid = (int)($_POST['question_id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            // Ownership check: only delete if question belongs to this company's assessment
            $pdo->prepare("DELETE iaq FROM industry_assessment_questions iaq JOIN industry_assessments ia ON ia.id = iaq.assessment_id WHERE iaq.id = ? AND ia.company_id = ?")->execute([$qid, $companyId]);
            flash('success', 'Question removed.');
            header('Location: index.php#assessments'); exit;
        }
        if ($action === 'save_workshop') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $type = trim((string)($_POST['type'] ?? 'Technical Workshop'));
            $startTime = trim((string)($_POST['start_time'] ?? ''));
            $endTime = trim((string)($_POST['end_time'] ?? ''));
            $venue = trim((string)($_POST['venue_or_link'] ?? ''));
            $capacity = max(1, (int)($_POST['max_capacity'] ?? 100));
            $desc = trim((string)($_POST['description'] ?? ''));
            $audience = trim((string)($_POST['target_audience'] ?? ''));
            $skills = trim((string)($_POST['required_skills'] ?? ''));
            $instId = (int)($_POST['target_institute_id'] ?? 0) ?: null;
            $speaker = trim((string)($_POST['speaker_name'] ?? ''));
            $speakerDesig = trim((string)($_POST['speaker_designation'] ?? ''));
            $companyId = (int)($_SESSION['company_id'] ?? 0);

            if ($title === '' || $startTime === '' || $endTime === '' || $venue === '') throw new RuntimeException('Title, date/time, and venue/meeting link are required.');

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE workshops SET title=?, type=?, start_time=?, end_time=?, venue_or_link=?, max_capacity=?, description=?, target_audience=?, required_skills=?, target_institute_id=?, speaker_name=?, speaker_designation=? WHERE id=? AND company_id=?");
                $stmt->execute([$title, $type, $startTime, $endTime, $venue, $capacity, $desc, $audience, $skills, $instId, $speaker, $speakerDesig, $id, $companyId]);
                flash('success', 'Workshop updated.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO workshops (company_id, title, type, start_time, end_time, venue_or_link, max_capacity, description, target_audience, required_skills, target_institute_id, speaker_name, speaker_designation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$companyId, $title, $type, $startTime, $endTime, $venue, $capacity, $desc, $audience, $skills, $instId, $speaker, $speakerDesig]);
                flash('success', 'Campus Workshop / Webinar created successfully.');
            }
            header('Location: index.php#workshops'); exit;
        }
        if ($action === 'toggle_shortlist') {
            $candId = (int)($_POST['candidate_id'] ?? 0);
            $companyId = (int)($_SESSION['company_id'] ?? 0);
            $check = $pdo->prepare("SELECT id FROM industry_shortlists WHERE company_id = ? AND candidate_id = ?");
            $check->execute([$companyId, $candId]);
            if ($check->fetchColumn()) {
                $pdo->prepare("DELETE FROM industry_shortlists WHERE company_id = ? AND candidate_id = ?")->execute([$companyId, $candId]);
                flash('success', 'Candidate removed from shortlist.');
            } else {
                $pdo->prepare("INSERT INTO industry_shortlists (company_id, candidate_id) VALUES (?, ?)")->execute([$companyId, $candId]);
                flash('success', 'Candidate shortlisted successfully.');
            }
            header('Location: index.php#candidates'); exit;
        }
        if ($action === 'mark_notifications_read') {
            sbMarkAllRead($pdo, (int)($_SESSION['user_id'] ?? 0));
            header('Location: index.php#notifications'); exit;
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Industry dashboard action failed: ' . $ex->getMessage());
        flash('error', 'Something went wrong. Please try again.');
        header('Location: index.php'); exit;
    }
}

$csrfToken = csrfToken();

$flash = $_SESSION['industry_flash'] ?? null;
unset($_SESSION['industry_flash']);

$company = $pdo ? $pdo->prepare('SELECT * FROM companies WHERE id = ? LIMIT 1') : null;
if ($company instanceof PDOStatement) { $company->execute([(int)($_SESSION['company_id'] ?? 0)]); $company = $company->fetch() ?: []; } else { $company = []; }
$companyId = (int)($company['id'] ?? ($_SESSION['company_id'] ?? 0));

$candidates = [];
$opportunities = [];
$institutes = [];
$skills = [];
$applications = [];
$collaborations = [];
$evaluations = [];
$notifications = [];
$messages = [];
$trend = [];
$skillDemand = [];
$industryAssessments = [];
$assessmentQuestions = [];
$workshops = [];

if ($pdo) {
    $candidates = $pdo->query("SELECT c.*, COALESCE(i.name,c.institute) institute_name, sp.degree, sp.branch, sp.semester, sp.cgpa, sp.career_interest, sp.preferred_role, (SELECT COUNT(*) FROM industry_shortlists isl WHERE isl.company_id={$companyId} AND isl.candidate_id=c.id) is_shortlisted, COALESCE((SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM candidate_skills cs JOIN skills s ON s.id=cs.skill_id WHERE cs.candidate_id=c.id), JSON_UNQUOTE(JSON_EXTRACT(c.skills,'$'))) skill_text FROM candidates c LEFT JOIN institutes i ON i.id=c.institute_id LEFT JOIN student_profiles sp ON sp.candidate_id=c.id ORDER BY is_shortlisted DESC, c.match_score DESC, c.name")->fetchAll();
    $opportunities = $pdo->query("SELECT o.*, COUNT(a.id) application_count, COALESCE(MAX(a.match_score),0) top_match, (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM opportunity_skills os JOIN skills s ON s.id=os.skill_id WHERE os.opportunity_id=o.id) mapped_skills FROM opportunities o LEFT JOIN applications a ON a.opportunity_id=o.id WHERE o.company_id={$companyId} GROUP BY o.id ORDER BY o.created_at DESC, o.id DESC")->fetchAll();
    $institutes = $pdo->query("SELECT i.*, COUNT(c.id) candidate_count FROM institutes i LEFT JOIN candidates c ON c.institute_id=i.id GROUP BY i.id ORDER BY candidate_count DESC, i.name")->fetchAll();
    $skills = $pdo->query("SELECT s.*, COUNT(DISTINCT os.opportunity_id) opportunity_count, COUNT(DISTINCT cs.candidate_id) candidate_count FROM skills s LEFT JOIN opportunity_skills os ON os.skill_id=s.id LEFT JOIN candidate_skills cs ON cs.skill_id=s.id GROUP BY s.id ORDER BY opportunity_count DESC, candidate_count DESC, s.name")->fetchAll();
    $applications = $pdo->query("SELECT a.*, c.name candidate_name, c.institute, o.title opportunity_title, o.type opportunity_type, iaa.total_score assessment_score, iaa.percentage assessment_pct, iaa.is_passed assessment_passed, iaa.completed_at assessment_date FROM applications a JOIN candidates c ON c.id=a.candidate_id JOIN opportunities o ON o.id=a.opportunity_id LEFT JOIN industry_assessments ia ON ia.opportunity_id = o.id LEFT JOIN industry_assessment_attempts iaa ON (iaa.assessment_id = ia.id AND iaa.candidate_id = c.id AND iaa.status = 'Completed') WHERE o.company_id={$companyId} ORDER BY a.updated_at DESC, a.id DESC")->fetchAll();
    $collaborations = $pdo->query("SELECT * FROM collaboration_requests WHERE company_id={$companyId} ORDER BY created_at DESC, id DESC")->fetchAll();
   $evalStmt = $pdo->prepare("
    SELECT
        e.*,
        c.name AS candidate_name,
        o.title AS opportunity_title
    FROM evaluations e
    JOIN candidates c ON c.id = e.candidate_id
    JOIN opportunities o ON o.id = e.opportunity_id
    WHERE o.company_id = ?
    ORDER BY e.evaluated_at DESC, e.id DESC
");

$evalStmt->execute([$companyId]);
$evaluations = $evalStmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications = sbGetNotificationsForUser($pdo, (int)($_SESSION['user_id'] ?? 0), 12);
    $messages = sbGetMessagesForUser($pdo, (int)($_SESSION['user_id'] ?? 0), 20);
    $trendStmt = $pdo->query("SELECT DATE(applied_at) day, COUNT(*) total, SUM(o.type='Full-time Job') jobs, SUM(o.type='Internship') internships FROM applications a JOIN opportunities o ON o.id=a.opportunity_id WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(applied_at) ORDER BY day");
    $trend = $trendStmt->fetchAll();
    $skillDemand = array_slice($skills, 0, 8);
    $learningPrograms = $pdo->query("SELECT * FROM learning_programs WHERE company_id={$companyId} ORDER BY created_at DESC, id DESC")->fetchAll();
    foreach ($learningPrograms as &$lp) { $ms = $pdo->prepare('SELECT id,module_title,description,order_num FROM learning_program_modules WHERE program_id=? ORDER BY order_num,id'); $ms->execute([(int)$lp['id']]); $lp['modules'] = $ms->fetchAll(); }
    unset($lp);
    $industryResources = $pdo->query("SELECT ir.*, (SELECT COUNT(*) FROM resource_acknowledgements ra WHERE ra.resource_id = ir.id) ack_count FROM industry_resources ir WHERE ir.company_id={$companyId} ORDER BY ir.created_at DESC")->fetchAll();
    $learningStudents = $pdo->query("SELECT le.*, c.name candidate_name, c.email candidate_email, lp.title program_title, (SELECT COUNT(*) FROM learning_progress lpr WHERE lpr.enrollment_id=le.id AND lpr.status='Completed') comp_mods, (SELECT COUNT(*) FROM learning_program_modules lpm WHERE lpm.program_id=lp.id) tot_mods FROM learning_enrollments le JOIN candidates c ON c.id=le.candidate_id JOIN learning_programs lp ON lp.id=le.program_id WHERE lp.company_id={$companyId} ORDER BY le.enrolled_at DESC")->fetchAll();
    $industryAssessments = $pdo->query("SELECT ia.*, o.title opp_title, (SELECT COUNT(*) FROM industry_assessment_questions WHERE assessment_id=ia.id) question_count, (SELECT COUNT(*) FROM industry_assessment_attempts WHERE assessment_id=ia.id) attempt_count FROM industry_assessments ia LEFT JOIN opportunities o ON o.id=ia.opportunity_id WHERE ia.company_id={$companyId} ORDER BY ia.created_at DESC")->fetchAll();
    if (!empty($industryAssessments)) {
        $assIds = implode(',', array_column($industryAssessments, 'id'));
        if ($assIds) {
            $assessmentQuestions = $pdo->query("SELECT * FROM industry_assessment_questions WHERE assessment_id IN ({$assIds}) ORDER BY order_num ASC, id ASC")->fetchAll();
        }
    }
    $workshops = $pdo->query("SELECT w.*, i.name institute_name, (SELECT COUNT(*) FROM workshop_registrations WHERE workshop_id=w.id) reg_count, (SELECT COUNT(*) FROM workshop_registrations WHERE workshop_id=w.id AND status='Attended') attended_count FROM workshops w LEFT JOIN institutes i ON i.id=w.target_institute_id WHERE w.company_id={$companyId} ORDER BY w.start_time DESC")->fetchAll();
} else {
    $learningPrograms = [];
    $industryResources = [];
    $learningStudents = [];
    $industryAssessments = [];
    $assessmentQuestions = [];
    $workshops = [];
}

$stats = [
    'candidates' => count($candidates),
    'jobs' => count(array_filter($opportunities, fn($o)=>(string)$o['type']==='Full-time Job')),
    'internships' => count(array_filter($opportunities, fn($o)=>(string)$o['type']==='Internship')),
    'applications' => count($applications),
    'shortlisted' => count(array_filter($applications, fn($a)=>(string)$a['status']==='Shortlisted')),
    'interviews' => count(array_filter($applications, fn($a)=>(string)$a['status']==='Interview')),
    'selected' => count(array_filter($applications, fn($a)=>(string)$a['status']==='Selected')),
    'active_jobs' => count(array_filter($opportunities, fn($o)=>(string)$o['type']==='Full-time Job' && (string)$o['status']==='Active')),
    'active_internships' => count(array_filter($opportunities, fn($o)=>(string)$o['type']==='Internship' && (string)$o['status']==='Active')),
    'learning' => count($learningPrograms),
];
$avgMatch = $applications ? round(array_sum(array_map(fn($a)=>(float)$a['match_score'],$applications))/count($applications)) : 0;
$unreadNotifications = count(array_filter($notifications, fn($n)=>(int)$n['is_read']===0));
$departments = array_values(array_unique(array_filter(array_map(fn($o)=>(string)($o['department'] ?? ''),$opportunities))));
$skillOptions = array_values(array_unique(array_map(fn($s)=>(string)$s['name'],$skills)));
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');

function icon(string $name, int $size=20): string {
    $paths = [
        'home'=>'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'book'=>'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15z"/><path d="M6.5 17H20"/>',
        'target'=>'<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v2M22 12h-2M12 22v-2M2 12h2"/>',
        'gap'=>'<path d="M4 19V9M10 19V5M16 19v-3M22 19H2"/><path d="m14 8 2-2 2 2 3-3"/>',
        'briefcase'=>'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/>',
        'cap'=>'<path d="m2 10 10-5 10 5-10 5z"/><path d="M6 12v5c3 2 9 2 12 0v-5M22 10v6"/>',
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'building'=>'<path d="M3 21h18M5 21V5h10v16M15 9h4v12M8 8h4M8 12h4M8 16h4"/>',
        'file'=>'<path d="M6 2h9l4 4v16H6z"/><path d="M15 2v5h5M9 12h6M9 16h6"/>',
        'handshake'=>'<path d="m3 12 4-4 5 5 5-5 4 4-4 4-5-3-5 3z"/><path d="m7 8 2-2 3 2 3-2 2 2"/>',
        'star'=>'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>',
        'forecast'=>'<path d="M4 19V5M4 19h17M7 15l4-5 3 2 5-7"/>',
        'analytics'=>'<path d="M4 19V9M10 19V5M16 19v-8M22 19H2"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'edit'=>'<path d="m4 20 4.5-1 10-10a2 2 0 0 0-3-3l-10 10zM14 7l3 3"/>',
        'trash'=>'<path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"/>',
        'eye'=>'<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/>',
        'arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>',
        'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'spark'=>'<path d="m12 3 1.7 5.3L19 10l-5.3 1.7L12 17l-1.7-5.3L5 10l5.3-1.7z"/>',
        'logout'=>'<path d="M10 17l5-5-5-5M15 12H3M21 19V5a2 2 0 0 0-2-2h-6"/>',
    ];
    $p = $paths[$name] ?? $paths['spark'];
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SkillBridge | Industry Portal</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-logo"><img src="../assets/skillbridge-logo.jpeg" alt="SkillBridge"></div>
      <div><strong>SKILL<span>BRIDGE</span></strong><small>Connect • Collaborate • Build</small></div>
    </div>
    <nav class="nav">
      <a class="nav-item active" href="#dashboard" data-section="dashboard"><?= icon('home') ?><span>Dashboard</span></a>
      <a class="nav-item" href="#matching" data-section="matching"><?= icon('target') ?><span>Smart Matching</span><b class="ai-pill">AI</b></a>
      <a class="nav-item" href="#skill-gap" data-section="skill-gap"><?= icon('gap') ?><span>Skill Gap Analyzer</span></a>
      <a class="nav-item" href="#jobs" data-section="jobs"><?= icon('briefcase') ?><span>Jobs</span></a>
      <a class="nav-item" href="#internships" data-section="internships"><?= icon('cap') ?><span>Internships</span></a>
      <a class="nav-item" href="#assessments" data-section="assessments"><?= icon('check') ?><span>Pre-Placement Assessments</span></a>
      <a class="nav-item" href="#workshops" data-section="workshops"><?= icon('users') ?><span>Campus Workshops</span></a>
      <a class="nav-item" href="#learning" data-section="learning"><?= icon('book') ?><span>Learning Programs</span></a>
      <a class="nav-item" href="#resources" data-section="resources"><?= icon('file') ?><span>Industry Resources</span></a>
      <a class="nav-item" href="#candidates" data-section="candidates"><?= icon('users') ?><span>Candidates</span></a>
      <a class="nav-item" href="#institutes" data-section="institutes"><?= icon('building') ?><span>Institute Talent</span></a>
      <a class="nav-item" href="#applications" data-section="applications"><?= icon('file') ?><span>Applications</span></a>
      <a class="nav-item" href="#collaboration" data-section="collaboration"><?= icon('handshake') ?><span>Collaboration</span></a>
      <a class="nav-item" href="#feedback" data-section="feedback"><?= icon('star') ?><span>Feedback & Evaluation</span></a>
      <a class="nav-item" href="#forecast" data-section="forecast"><?= icon('forecast') ?><span>Hiring Forecast</span></a>
      <a class="nav-item" href="#analytics" data-section="analytics"><?= icon('analytics') ?><span>Analytics</span></a>
      <div class="nav-divider"></div>
      <a class="nav-item" href="#messages" data-section="messages"><?= icon('mail') ?><span>Messages</span><?php if(count($messages)): ?><b class="count-badge"><?= count($messages) ?></b><?php endif; ?></a>
      <a class="nav-item" href="#notifications" data-section="notifications"><?= icon('bell') ?><span>Notifications</span><?php if($unreadNotifications): ?><b class="count-badge"><?= $unreadNotifications ?></b><?php endif; ?></a>
    </nav>
    <div class="institution-card">
      <div class="leaf">✦</div>
      <div><strong>Ministry of Ayush</strong><small>Government of India</small><strong class="institute-name">All India Institute of Ayurveda</strong></div>
    </div>
    <div class="company-mini">
      <div class="avatar"><?= e(strtoupper(substr((string)($company['name'] ?? 'I'),0,1))) ?></div>
      <div><strong><?= e($company['name'] ?? 'Industry Partner') ?></strong><small><?= e($company['admin_name'] ?? 'Company Admin') ?></small></div>
      <span>⌄</span>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <button class="mobile-menu" id="mobileMenu" aria-label="Open menu"><?= icon('menu',22) ?></button>
      <div class="global-search"><span><?= icon('search',19) ?></span><input id="globalSearch" placeholder="Search candidates, jobs, internships, skills..." autocomplete="off"><div id="searchResults" class="search-results"></div></div>
      <div class="top-actions">
        <button class="icon-btn" data-go="notifications" title="Notifications"><?= icon('bell',20) ?><?php if($unreadNotifications): ?><em><?= $unreadNotifications ?></em><?php endif; ?></button>
        <button class="icon-btn" data-go="messages" title="Messages"><?= icon('mail',20) ?><?php if(count($messages)): ?><em><?= count($messages) ?></em><?php endif; ?></button>
        <div class="account"><div class="avatar large"><?= e(strtoupper(substr((string)($company['name'] ?? 'I'),0,1))) ?></div><div><strong><?= e($company['name'] ?? 'Industry Partner') ?></strong><small><?= e($company['admin_name'] ?? 'Company Admin') ?></small></div><span>⌄</span></div>
      </div>
    <a href="../logout.php" class="portal-logout">Logout</a></header>

    <?php if($flash): ?><div class="flash <?= e($flash['type']) ?>" id="flash"><?= e($flash['message']) ?></div><?php endif; ?>

    <div class="content">
      <section class="page-section active" id="dashboard">
        <div class="welcome-grid">
          <article class="welcome-card">
            <div class="welcome-copy">
              <span class="eyebrow">INDUSTRY COLLABORATION PORTAL</span>
              <h1><?= e($greeting) ?>,<br><strong><?= e($company['name'] ?? 'Industry Partner') ?></strong></h1>
              <p>Connect talent. Collaborate with academia. Build a stronger future workforce.</p>
              <div class="welcome-links"><span>Connect</span><i>•</i><span>Collaborate</span><i>•</i><span>Build</span></div>
            </div>
            <div class="welcome-art"><div class="art-building"></div><div class="art-leaf">🌿</div><div class="art-text">“Ayurveda for<br>a healthier tomorrow”</div></div>
          </article>
          <aside class="quick-card">
            <div class="section-title light"><div><h3>Quick Actions</h3><p>Get things done faster</p></div><?= icon('spark',22) ?></div>
            <button class="quick-action" data-open-opportunity="job"><span class="quick-icon blue"><?= icon('file') ?></span><span><strong>Post a Job</strong><small>Create new job opening</small></span><?= icon('arrow',18) ?></button>
            <button class="quick-action" data-open-opportunity="internship"><span class="quick-icon teal"><?= icon('cap') ?></span><span><strong>Post an Internship</strong><small>Create new internship</small></span><?= icon('arrow',18) ?></button>
            <button class="quick-action" data-go="collaboration"><span class="quick-icon cyan"><?= icon('handshake') ?></span><span><strong>Add Collaboration Request</strong><small>Connect with institutes</small></span><?= icon('arrow',18) ?></button>
            <button class="quick-action" data-go="candidates"><span class="quick-icon violet"><?= icon('users') ?></span><span><strong>View Candidates</strong><small>Search & shortlist talent</small></span><?= icon('arrow',18) ?></button>
          </aside>
        </div>

        <div class="stats-grid">
          <div class="stat-card"><span class="stat-icon blue"><?= icon('users') ?></span><div><small>Total Candidates</small><strong><?= $stats['candidates'] ?></strong><em>Live database</em></div></div>
          <div class="stat-card"><span class="stat-icon green"><?= icon('briefcase') ?></span><div><small>Active Jobs</small><strong><?= $stats['active_jobs'] ?></strong><em><?= $stats['jobs'] ?> total jobs</em></div></div>
          <div class="stat-card"><span class="stat-icon violet"><?= icon('cap') ?></span><div><small>Internships</small><strong><?= $stats['active_internships'] ?></strong><em><?= $stats['internships'] ?> total internships</em></div></div>
          <div class="stat-card"><span class="stat-icon orange"><?= icon('file') ?></span><div><small>Applications</small><strong><?= $stats['applications'] ?></strong><em><?= $stats['shortlisted'] ?> shortlisted</em></div></div>
          <div class="stat-card"><span class="stat-icon teal"><?= icon('target') ?></span><div><small>Avg. Match Score</small><strong><?= $avgMatch ?>%</strong><em><?= $stats['interviews'] ?> interviews</em></div></div>
        </div>

        <div class="dashboard-grid two-one">
          <article class="card opportunity-card">
            <div class="card-head"><div><h3>Recent Opportunities</h3><p>Latest jobs and internships from your portal</p></div><button class="text-btn" data-go="jobs">View all <?= icon('arrow',15) ?></button></div>
            <div class="opportunity-list">
              <?php foreach(array_slice($opportunities,0,5) as $o): ?>
                <div class="opportunity-row">
                  <span class="opp-icon <?= $o['type']==='Internship'?'teal':'blue' ?>"><?= $o['type']==='Internship'?icon('cap'):icon('briefcase') ?></span>
                  <div class="opp-main"><strong><?= e($o['title']) ?></strong><small><?= e($o['location'] ?: 'Location not specified') ?> <b>•</b> <?= e($o['mapped_skills'] ?: $o['required_skills'] ?: 'Skills not specified') ?></small></div>
                  <div class="opp-meta"><strong><?= (int)$o['application_count'] ?> applicants</strong><small><?= e($o['deadline'] ?: 'No deadline') ?></small></div>
                  <span class="status <?= strtolower((string)$o['status']) ?>"><?= e($o['status']) ?></span>
                </div>
              <?php endforeach; ?>
              <?php if(!$opportunities): ?><div class="empty-state compact">No opportunities yet. Create your first job or internship.</div><?php endif; ?>
            </div>
          </article>
          <article class="card skill-card">
            <div class="card-head"><div><h3>Skill Demand</h3><p>Demand from your opportunities</p></div><button class="text-btn" data-go="analytics">View all <?= icon('arrow',15) ?></button></div>
            <div class="skill-bars">
              <?php foreach($skillDemand as $s): $pct = $stats['jobs']+$stats['internships'] ? min(100, round(((int)$s['opportunity_count']/max(1,$stats['jobs']+$stats['internships']))*100)) : 0; ?>
                <div class="skill-bar"><span class="skill-dot">◆</span><div><strong><?= e($s['name']) ?></strong><div class="bar"><i style="width:<?= $pct ?>%"></i></div></div><b><?= $pct ?>%</b></div>
              <?php endforeach; ?>
              <?php if(!$skillDemand): ?><div class="empty-state compact">Skill demand will appear after opportunities have required skills.</div><?php endif; ?>
            </div>
          </article>
        </div>

        <div class="dashboard-grid two-one">
          <article class="card candidates-strip">
            <div class="card-head"><div><h3>Top Candidates</h3><p>Highest match scores in the current talent pool</p></div><button class="text-btn" data-go="candidates">View all <?= icon('arrow',15) ?></button></div>
            <div class="candidate-cards">
              <?php foreach(array_slice($candidates,0,5) as $c): $initials=''; foreach(array_slice(preg_split('/\s+/',trim((string)$c['name'])),0,2) as $n){$initials.=strtoupper(substr($n,0,1));} ?>
                <button class="candidate-mini" data-candidate-id="<?= (int)$c['id'] ?>"><span class="candidate-avatar"> <?= e($initials) ?> </span><strong><?= e($c['name']) ?></strong><small><?= e($c['institute_name'] ?: $c['institute']) ?></small><em><?= round((float)$c['match_score']) ?>% Match</em></button>
              <?php endforeach; ?>
              <?php if(!$candidates): ?><div class="empty-state compact">No candidates available.</div><?php endif; ?>
            </div>
          </article>
          <article class="card chart-card">
            <div class="card-head"><div><h3>Applications Overview</h3><p>Last 14 days from MySQL</p></div></div>
            <div class="chart"><canvas id="applicationChart"></canvas></div>
          </article>
        </div>

        <div class="dashboard-grid activity-layout">
          <article class="card funnel-card">
            <div class="card-head"><div><h3>Hiring Pipeline</h3><p>Current application stages</p></div></div>
            <?php $funnel=[['Applied',$stats['applications'],'blue'],['Shortlisted',$stats['shortlisted'],'green'],['Interview',$stats['interviews'],'orange'],['Selected',$stats['selected'],'violet']]; foreach($funnel as [$label,$value,$tone]): $width=$stats['applications']?round(($value/$stats['applications'])*100):0; ?>
              <div class="funnel-row"><span class="funnel-icon <?= $tone ?>"><?= icon($label==='Applied'?'file':($label==='Shortlisted'?'check':($label==='Interview'?'target':'star')),16) ?></span><div><strong><?= e($label) ?></strong><div class="bar"><i class="<?= $tone ?>" style="width:<?= $width ?>%"></i></div></div><b><?= $value ?></b></div>
            <?php endforeach; ?>
          </article>
          <article class="card activity-card">
            <div class="card-head"><div><h3>Recent Activity</h3><p>Latest activity in the portal</p></div><button class="text-btn" data-go="applications">View all <?= icon('arrow',15) ?></button></div>
            <div class="activity-list">
              <?php foreach(array_slice($applications,0,4) as $a): ?><div class="activity-row"><span class="activity-dot blue"><?= icon('file',15) ?></span><div><strong>Application <?= e(strtolower($a['status'])) ?></strong><small><?= e($a['candidate_name']) ?> → <?= e($a['opportunity_title']) ?></small></div><time><?= e(date('d M',strtotime((string)$a['updated_at']))) ?></time></div><?php endforeach; ?>
              <?php foreach(array_slice($collaborations,0,max(0,4-count(array_slice($applications,0,4)))) as $c): ?><div class="activity-row"><span class="activity-dot teal"><?= icon('handshake',15) ?></span><div><strong>Collaboration request</strong><small><?= e($c['institute']) ?> • <?= e($c['collaboration_type']) ?></small></div><time><?= e(date('d M',strtotime((string)$c['created_at']))) ?></time></div><?php endforeach; ?>
              <?php if(!$applications && !$collaborations): ?><div class="empty-state compact">No recent activity yet.</div><?php endif; ?>
            </div>
          </article>
        </div>
      </section>

      <section class="page-section" id="jobs"><div class="page-title"><div><span class="eyebrow">OPPORTUNITY MANAGEMENT</span><h2>Jobs</h2><p>Create, manage and monitor industry hiring opportunities.</p></div><button class="btn primary" data-open-opportunity="job"><?= icon('plus',18) ?> Post New Job</button></div><div class="mini-kpis"><span><b><?= $stats['active_jobs'] ?></b> Active</span><span><b><?= count(array_filter($opportunities,fn($o)=>$o['type']==='Full-time Job'&&$o['status']==='Draft')) ?></b> Drafts</span><span><b><?= count(array_filter($opportunities,fn($o)=>$o['type']==='Full-time Job'&&$o['status']==='Closed')) ?></b> Closed</span><span><b><?= array_sum(array_map(fn($o)=>(int)$o['application_count'],array_filter($opportunities,fn($o)=>$o['type']==='Full-time Job'))) ?></b> Applications</span></div><div class="toolbar"><input class="input" id="jobSearch" placeholder="Search jobs..."><select class="input" id="jobDepartment"><option value="">All departments</option><?php foreach($departments as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></select><select class="input" id="jobStatus"><option value="">All status</option><option>Active</option><option>Draft</option><option>Closed</option></select></div><div class="card table-card"><div class="table-scroll"><table id="jobsTable"><thead><tr><th>Opportunity</th><th>Department</th><th>Skills</th><th>Applications</th><th>Deadline</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($opportunities as $o): if($o['type']!=='Full-time Job') continue; ?><tr data-search="<?= e(strtolower($o['title'].' '.$o['department'].' '.$o['mapped_skills'].' '.$o['required_skills'])) ?>" data-department="<?= e($o['department']) ?>" data-status="<?= e($o['status']) ?>"><td><strong><?= e($o['title']) ?></strong><small><?= e($o['location'] ?: 'Location not specified') ?> • <?= e($o['qualification'] ?: 'Qualification not specified') ?></small></td><td><?= e($o['department'] ?: '—') ?></td><td><span class="skill-text"><?= e($o['mapped_skills'] ?: $o['required_skills'] ?: '—') ?></span></td><td><b><?= (int)$o['application_count'] ?></b><small>Top match <?= round((float)$o['top_match']) ?>%</small></td><td><?= e($o['deadline'] ?: '—') ?></td><td><span class="status <?= strtolower((string)$o['status']) ?>"><?= e($o['status']) ?></span></td><td><div class="row-actions"><button data-view-opportunity="<?= (int)$o['id'] ?>" title="View"><?= icon('eye',17) ?></button><button data-edit-opportunity="<?= (int)$o['id'] ?>" title="Edit"><?= icon('edit',17) ?></button><button data-delete-opportunity="<?= (int)$o['id'] ?>" title="Delete"><?= icon('trash',17) ?></button></div></td></tr><?php endforeach; ?></tbody></table></div></div></section>

      <section class="page-section" id="internships"><div class="page-title"><div><span class="eyebrow">OPPORTUNITY MANAGEMENT</span><h2>Internships</h2><p>Manage internships and connect students with practical industry exposure.</p></div><button class="btn primary" data-open-opportunity="internship"><?= icon('plus',18) ?> Post Internship</button></div><div class="mini-kpis"><span><b><?= $stats['active_internships'] ?></b> Active</span><span><b><?= count(array_filter($opportunities,fn($o)=>$o['type']==='Internship'&&$o['status']==='Draft')) ?></b> Drafts</span><span><b><?= count(array_filter($opportunities,fn($o)=>$o['type']==='Internship'&&$o['status']==='Closed')) ?></b> Closed</span><span><b><?= array_sum(array_map(fn($o)=>(int)$o['application_count'],array_filter($opportunities,fn($o)=>$o['type']==='Internship'))) ?></b> Applications</span></div><div class="toolbar"><input class="input" id="internshipSearch" placeholder="Search internships..."><select class="input" id="internshipStatus"><option value="">All status</option><option>Active</option><option>Draft</option><option>Closed</option></select></div><div class="card table-card"><div class="table-scroll"><table id="internshipsTable"><thead><tr><th>Opportunity</th><th>Duration</th><th>Skills</th><th>Applications</th><th>Deadline</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($opportunities as $o): if($o['type']!=='Internship') continue; ?><tr data-search="<?= e(strtolower($o['title'].' '.$o['location'].' '.$o['mapped_skills'].' '.$o['required_skills'])) ?>" data-status="<?= e($o['status']) ?>"><td><strong><?= e($o['title']) ?></strong><small><?= e($o['location'] ?: 'Location not specified') ?> • <?= e($o['eligibility'] ?: 'Eligibility not specified') ?></small></td><td><?= e($o['duration'] ?: '—') ?></td><td><span class="skill-text"><?= e($o['mapped_skills'] ?: $o['required_skills'] ?: '—') ?></span></td><td><b><?= (int)$o['application_count'] ?></b><small>Top match <?= round((float)$o['top_match']) ?>%</small></td><td><?= e($o['deadline'] ?: '—') ?></td><td><span class="status <?= strtolower((string)$o['status']) ?>"><?= e($o['status']) ?></span></td><td><div class="row-actions"><button data-view-opportunity="<?= (int)$o['id'] ?>"><?= icon('eye',17) ?></button><button data-edit-opportunity="<?= (int)$o['id'] ?>"><?= icon('edit',17) ?></button><button data-delete-opportunity="<?= (int)$o['id'] ?>"><?= icon('trash',17) ?></button></div></td></tr><?php endforeach; ?></tbody></table></div></div></section>

      <!-- PRE-PLACEMENT ASSESSMENTS SECTION -->
      <section class="page-section" id="assessments">
        <div class="page-title">
          <div>
            <span class="eyebrow">RECRUITMENT SCREENING</span>
            <h2>Pre-Placement Assessments</h2>
            <p>Create custom screening tests linked to job openings and evaluate applicant technical competency.</p>
          </div>
          <button class="btn primary" onclick="document.getElementById('assessmentModal').style.display='flex'"><?= icon('plus',18) ?> Create Assessment</button>
        </div>

        <div class="mini-kpis">
          <span><b><?= count($industryAssessments) ?></b> Assessments Created</span>
          <span><b><?= array_sum(array_map(fn($a)=>(int)$a['question_count'], $industryAssessments)) ?></b> Total Questions</span>
          <span><b><?= array_sum(array_map(fn($a)=>(int)$a['attempt_count'], $industryAssessments)) ?></b> Student Attempts</span>
        </div>

        <div class="collab-grid">
          <?php foreach($industryAssessments as $ass): 
            $currQuestions = array_filter($assessmentQuestions, fn($q)=>(int)$q['assessment_id']===(int)$ass['id']);
          ?>
            <article class="card collab-item" style="padding:24px;">
              <div class="collab-top">
                <span class="collab-icon blue"><?= icon('check',22) ?></span>
                <span class="status <?= strtolower((string)$ass['status']) ?>"><?= e($ass['status']) ?></span>
              </div>
              <h3 style="font-size:18px; margin:4px 0;"><?= e($ass['title']) ?></h3>
              <p style="margin:0 0 8px; color:#2563eb; font-weight:600; font-size:13px;">Linked Opportunity: <?= e($ass['opp_title'] ?: 'General Industry Screening') ?></p>
              <div style="display:flex; gap:16px; background:#f8fafc; padding:10px 14px; border-radius:8px; font-size:12px; margin-bottom:12px; color:#475569;">
                <span>⏱ Duration: <b><?= (int)$ass['duration_mins'] ?> mins</b></span>
                <span>🎯 Passing Score: <b><?= (float)$ass['passing_score_pct'] ?>%</b></span>
                <span>❓ Questions: <b><?= (int)$ass['question_count'] ?></b></span>
              </div>
              <div class="collab-message" style="margin-bottom:16px;"><?= e($ass['description'] ?: 'No detailed instructions provided.') ?></div>

              <div style="border-top:1px solid #e2e8f0; pt-12px; margin-top:12px;">
                <strong style="display:block; font-size:13px; margin-bottom:8px; color:#334155;">Questions (<?= count($currQuestions) ?>):</strong>
                <?php foreach(array_slice($currQuestions, 0, 3) as $q): ?>
                  <div style="font-size:12px; color:#475569; margin-bottom:6px; background:#fff; padding:6px 10px; border:1px solid #e2e8f0; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                    <div><b>Q:</b> <?= e($q['question']) ?> <small style="color:#2563eb;">(<?= e($q['skill_topic']?:'General') ?> • Answer: <?= e($q['correct_option']) ?>)</small></div>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" style="display:inline;">
                      <input type="hidden" name="action" value="delete_assessment_question">
                      <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                      <button type="submit" onclick="return confirm('Remove question?')" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px;">×</button>
                    </form>
                  </div>
                <?php endforeach; ?>
                <?php if(!$currQuestions): ?><p style="font-size:12px; color:#94a3b8;">No questions added yet to this assessment.</p><?php endif; ?>
                
                <button onclick="openAddQuestionModal(<?= (int)$ass['id'] ?>, '<?= e(addslashes($ass['title'])) ?>')" class="btn secondary" style="padding:4px 10px; font-size:11px; margin-top:8px;">+ Add Question</button>
              </div>
            </article>
          <?php endforeach; ?>
          <?php if(!$industryAssessments): ?>
            <div class="card empty-state" style="grid-column: 1/-1;">No pre-placement assessments created yet. Click "Create Assessment" to build screening tests for candidates.</div>
          <?php endif; ?>
        </div>
      </section>

      <!-- CAMPUS WORKSHOPS & WEBINARS SECTION -->
      <section class="page-section" id="workshops">
        <div class="page-title">
          <div>
            <span class="eyebrow">ACADEMIA ENGAGEMENT</span>
            <h2>Campus Workshops & Webinars</h2>
            <p>Schedule guest lectures, webinars, masterclasses, and FDPs for partner institutes.</p>
          </div>
          <button class="btn primary" onclick="document.getElementById('workshopModal').style.display='flex'"><?= icon('plus',18) ?> Schedule Workshop</button>
        </div>

        <div class="mini-kpis">
          <span><b><?= count($workshops) ?></b> Scheduled Workshops</span>
          <span><b><?= array_sum(array_map(fn($w)=>(int)$w['reg_count'], $workshops)) ?></b> Student Registrations</span>
          <span><b><?= array_sum(array_map(fn($w)=>(int)$w['attended_count'], $workshops)) ?></b> Attended</span>
        </div>

        <div class="collab-grid">
          <?php foreach($workshops as $ws): ?>
            <article class="card collab-item" style="padding:24px;">
              <div class="collab-top">
                <span class="collab-icon teal"><?= icon('users',22) ?></span>
                <span class="status <?= strtolower((string)$ws['status']) ?>"><?= e($ws['status']) ?></span>
              </div>
              <h3 style="font-size:18px; margin:4px 0;"><?= e($ws['title']) ?></h3>
              <p style="margin:0 0 8px; color:#0d9488; font-weight:600; font-size:13px;">Type: <?= e($ws['type']) ?> | Target: <?= e($ws['institute_name'] ?: 'All Partner Institutes') ?></p>
              <div style="background:#f8fafc; padding:10px 14px; border-radius:8px; font-size:12px; margin-bottom:12px; color:#475569;">
                <div>📅 <b><?= e(date('d M Y, h:i A', strtotime((string)$ws['start_time']))) ?></b> to <b><?= e(date('h:i A', strtotime((string)$ws['end_time']))) ?></b></div>
                <div>📍 <b><?= e($ws['venue_or_link']) ?></b></div>
                <div>🎤 Speaker: <b><?= e($ws['speaker_name'] ?: 'Industry Expert') ?></b> (<?= e($ws['speaker_designation'] ?: 'Lead Engineer') ?>)</div>
                <div>👥 Capacity: <b><?= (int)$ws['reg_count'] ?> / <?= (int)$ws['max_capacity'] ?> Registered</b></div>
              </div>
              <div class="collab-message"><?= e($ws['description'] ?: 'No detailed session outline provided.') ?></div>
            </article>
          <?php endforeach; ?>
          <?php if(!$workshops): ?>
            <div class="card empty-state" style="grid-column: 1/-1;">No workshops or webinars scheduled yet. Click "Schedule Workshop" to invite students and faculty.</div>
          <?php endif; ?>
        </div>
      </section>

      <section class="page-section" id="learning"><div class="page-title"><div><span class="eyebrow">SKILL CLOSURE</span><h2>Learning Programs</h2><p>Publish company training, workshops, and certifications to help students close skill gaps.</p></div><button class="btn primary" id="newLearningBtn"><?= icon('plus',18) ?> New Learning Program</button></div><div class="mini-kpis"><span><b><?= count($learningPrograms) ?></b> Published Programs</span><span><b><?= count($learningStudents) ?></b> Total Enrolled Students</span><span><b><?= count(array_filter($learningStudents,fn($s)=>(int)$s['tot_mods']>0&&(int)$s['comp_mods']>=(int)$s['tot_mods'])) ?></b> Completed Students</span></div><div class="collab-grid"><?php foreach($learningPrograms as $lp): $enrolled = array_filter($learningStudents, fn($s)=>(int)$s['id']===(int)$lp['id']); ?>
<article class="card collab-item"><div class="collab-top"><span class="collab-icon blue"><?= icon('book',22) ?></span><span class="status <?= strtolower((string)$lp['status']) ?>"><?= e($lp['status']) ?></span></div><h3><?= e($lp['title']) ?></h3><p><strong><?= e($lp['type']) ?></strong> • <?= e($lp['duration'] ?: 'Self-paced') ?> • <?= e($lp['location'] ?: 'Online') ?></p><small>Target skills: <b><?= e($lp['target_skills'] ?: 'General') ?></b></small><div class="collab-message"><?= e($lp['description'] ?: 'No details provided.') ?></div><div style="margin-top:12px; background:#f8fafc; padding:10px; border-radius:8px; font-size:12px;"><strong>Enrolled Students (<?= count($enrolled) ?>):</strong><?php foreach(array_slice($enrolled,0,3) as $st): ?><div>• <?= e($st['candidate_name']) ?> — <b><?= $st['tot_mods']?round(($st['comp_mods']/$st['tot_mods'])*100):0 ?>% Progress</b></div><?php endforeach; ?><?php if(!$enrolled): ?><div>No students enrolled yet.</div><?php endif; ?></div><div class="row-actions" style="margin-top:12px;"><button data-edit-learning="<?= (int)$lp['id'] ?>"><?= icon('edit',16) ?> Edit</button><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" style="display:inline;"><input type="hidden" name="action" value="delete_learning_program"><input type="hidden" name="id" value="<?= (int)$lp['id'] ?>"><button type="submit" onclick="return confirm('Delete this program?')" style="color:#e53e3e;"><?= icon('trash',16) ?> Delete</button></form></div></article><?php endforeach; ?><?php if(!$learningPrograms): ?><div class="card empty-state">No learning programs published yet. Click "New Learning Program" to create one.</div><?php endif; ?></div></section>

      <section class="page-section" id="resources"><div class="page-title"><div><span class="eyebrow">ACADEMIA KNOWLEDGE EXCHANGE</span><h2>Industry Resources</h2><p>Publish question banks, technical curriculum recommendations and interview topics for institutes.</p></div><button class="btn primary" id="newResourceBtn"><?= icon('plus',18) ?> New Resource</button></div><div class="collab-grid"><?php foreach($industryResources as $ir): ?><article class="card collab-item"><div class="collab-top"><span class="collab-icon blue"><?= icon('file',22) ?></span><span class="status active"><?= e($ir['audience']) ?></span></div><h3><?= e($ir['title']) ?></h3><p>Topic: <b><?= e($ir['topic']) ?></b> | Acknowledgements: <b><?= (int)$ir['ack_count'] ?> Institutes</b></p><div class="collab-message"><?= e($ir['description'] ?: 'No details provided.') ?></div><?php if($ir['resource_url']): ?><div style="margin-top:8px;"><a href="<?= e($ir['resource_url']) ?>" target="_blank" style="color:#2563eb; font-size:12px; font-weight:700;">🌐 External Reference Link</a></div><?php endif; ?><div class="row-actions" style="margin-top:12px;"><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" style="display:inline;"><input type="hidden" name="action" value="delete_industry_resource"><input type="hidden" name="id" value="<?= (int)$ir['id'] ?>"><button type="submit" onclick="return confirm('Delete resource?')" style="color:#e53e3e;"><?= icon('trash',16) ?> Delete</button></form></div></article><?php endforeach; ?><?php if(!$industryResources): ?><div class="card empty-state">No industry resources published yet. Click "New Resource" to create one.</div><?php endif; ?></div></section>

      <section class="page-section" id="matching"><div class="page-title"><div><span class="eyebrow">INTELLIGENCE</span><h2>Smart Matching</h2><p>Compare candidate skills against real opportunity requirements.</p></div></div><div class="toolbar"><select class="input" id="matchingOpportunity"><option value="">Select an opportunity</option><?php foreach($opportunities as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['title'].' • '.$o['type']) ?></option><?php endforeach; ?></select></div><div class="card table-card"><div id="matchingContent" class="intelligence-list"><div class="empty-state">Select an opportunity to calculate skill compatibility from MySQL data.</div></div></div></section>

      <section class="page-section" id="skill-gap"><div class="page-title"><div><span class="eyebrow">INTELLIGENCE</span><h2>Skill Gap Analyzer</h2><p>See exactly which required skills a candidate has or is missing.</p></div></div><div class="toolbar"><select class="input" id="gapOpportunity"><option value="">Select an opportunity</option><?php foreach($opportunities as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['title']) ?></option><?php endforeach; ?></select><select class="input" id="gapCandidate"><option value="">Select a candidate</option><?php foreach($candidates as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div id="gapContent" class="gap-grid"><div class="card empty-state">Choose a candidate and opportunity.</div></div></section>

      <section class="page-section" id="candidates"><div class="page-title"><div><span class="eyebrow">TALENT POOL</span><h2>Candidates</h2><p>Search and inspect candidates from the MySQL talent pool.</p></div></div><div class="toolbar"><input class="input" id="candidateSearch" placeholder="Search candidate, institute, role or skill..."><select class="input" id="candidateInstitute"><option value="">All institutes</option><?php foreach($institutes as $i): ?><option><?= e($i['name']) ?></option><?php endforeach; ?></select><select class="input" id="candidateScore"><option value="0">Any match score</option><option value="80">80%+</option><option value="70">70%+</option><option value="60">60%+</option></select></div><div id="candidateGrid" class="candidate-grid"></div></section>

      <section class="page-section" id="institutes"><div class="page-title"><div><span class="eyebrow">ACADEMIA NETWORK</span><h2>Institute Talent</h2><p>Understand available student talent and skill supply by institute.</p></div></div><div class="institute-grid"><?php foreach($institutes as $i): ?><article class="card institute-card"><span class="institute-symbol"><?= icon('building',22) ?></span><h3><?= e($i['name']) ?></h3><p><?= e(($i['city'] ?? '').(($i['state'] ?? '')?', '.$i['state']:'')) ?></p><div class="institute-stats"><span><b><?= (int)$i['candidate_count'] ?></b><small>Available students</small></span><span><b><?= e($i['department'] ?: 'Multiple') ?></b><small>Department</small></span></div></article><?php endforeach; ?><?php if(!$institutes): ?><div class="card empty-state">No institutes in database.</div><?php endif; ?></div></section>

      <section class="page-section" id="applications"><div class="page-title"><div><span class="eyebrow">HIRING PIPELINE</span><h2>Applications</h2><p>Track candidate applications and update recruitment stages.</p></div><a href="../export/export_csv.php?type=applications" class="btn secondary"><?= icon('file',16) ?> Export CSV</a></div><div class="mini-kpis"><span><b><?= $stats['applications'] ?></b> Applications</span><span><b><?= $stats['shortlisted'] ?></b> Shortlisted</span><span><b><?= $stats['interviews'] ?></b> Interviews</span><span><b><?= $stats['selected'] ?></b> Selected</span></div><div class="toolbar"><input class="input" id="applicationSearch" placeholder="Search candidate or opportunity..."><select class="input" id="applicationStatus"><option value="">All status</option><option>Applied</option><option>Under Review</option><option>Shortlisted</option><option>Interview</option><option>Selected</option><option>Rejected</option><option>Withdrawn</option></select></div><div class="card table-card"><div class="table-scroll"><table id="applicationsTable"><thead><tr><th>Candidate</th><th>Opportunity</th><th>Match</th><th>Status Workflow</th><th>Applied Date</th></tr></thead><tbody><?php foreach($applications as $a): ?><tr data-search="<?= e(strtolower($a['candidate_name'].' '.$a['opportunity_title'].' '.$a['institute'])) ?>" data-status="<?= e($a['status']) ?>"><td><strong><?= e($a['candidate_name']) ?></strong><small><?= e($a['institute']) ?></small></td><td><?= e($a['opportunity_title']) ?><small><?= e($a['opportunity_type']) ?></small></td><td><span class="match-badge <?= ((float)$a['match_score']>=80?'high':((float)$a['match_score']>=60?'mid':'low')) ?>"><?= round((float)$a['match_score']) ?>%</span></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" style="display:inline-flex; align-items:center; gap:6px;"><input type="hidden" name="action" value="update_application_status"><input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>"><select class="input" name="status" onchange="this.form.submit()" style="padding:4px 8px; font-size:12px;"><option <?= $a['status']==='Applied'?'selected':'' ?>>Applied</option><option <?= $a['status']==='Under Review'?'selected':'' ?>>Under Review</option><option <?= $a['status']==='Shortlisted'?'selected':'' ?>>Shortlisted</option><option <?= $a['status']==='Interview'?'selected':'' ?>>Interview</option><option <?= $a['status']==='Selected'?'selected':'' ?>>Selected</option><option <?= $a['status']==='Rejected'?'selected':'' ?>>Rejected</option><option <?= $a['status']==='Withdrawn'?'selected':'' ?>>Withdrawn</option></select></form></td><td><?= e(date('d M Y',strtotime((string)$a['applied_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div></div></section>

      <section class="page-section" id="collaboration"><div class="page-title"><div><span class="eyebrow">INDUSTRY ↔ ACADEMIA</span><h2>Collaboration</h2><p>Build structured partnerships with institutes.</p></div><button class="btn primary" id="newCollabBtn"><?= icon('plus',18) ?> New Request</button></div><div class="collab-grid"><?php foreach($collaborations as $c): ?><article class="card collab-item"><div class="collab-top"><span class="collab-icon blue"><?= icon('handshake',22) ?></span><span class="status <?= strtolower((string)$c['status']) ?>"><?= e($c['status']) ?></span></div><h3><?= e($c['institute']) ?></h3><p><?= e($c['collaboration_type']) ?><?php if($c['event_date']): ?> • <?= e(date('d M Y',strtotime((string)$c['event_date']))) ?><?php endif; ?></p><small><?= e($c['focus_skills'] ?: 'No focus skills specified') ?></small><div class="collab-message"><?= e($c['message'] ?: 'No description provided.') ?></div></article><?php endforeach; ?><?php if(!$collaborations): ?><div class="card empty-state">No collaboration requests yet.</div><?php endif; ?></div></section>

      <section class="page-section" id="feedback"><div class="page-title"><div><span class="eyebrow">EVALUATION</span><h2>Feedback & Evaluation</h2><p>Review candidate performance using database-backed evaluations.</p></div></div><div class="evaluation-grid"><?php foreach($evaluations as $ev): ?><article class="card evaluation-card"><div class="eval-head"><div class="candidate-avatar small"><?= e(strtoupper(substr((string)$ev['candidate_name'],0,1))) ?></div><div><strong><?= e($ev['candidate_name']) ?></strong><small><?= e($ev['opportunity_title'] ?: 'General evaluation') ?></small></div><span><?= number_format((float)$ev['overall_score'],1) ?>/5</span></div><div class="score-row"><span>Technical <b><?= number_format((float)$ev['technical_score'],1) ?></b></span><span>Communication <b><?= number_format((float)$ev['communication_score'],1) ?></b></span><span>Problem solving <b><?= number_format((float)$ev['problem_solving_score'],1) ?></b></span><span>Teamwork <b><?= number_format((float)$ev['teamwork_score'],1) ?></b></span></div><p><?= e($ev['feedback'] ?: 'No written feedback.') ?></p><small>Evaluator: <?= e($ev['evaluator']) ?> • <?= e(date('d M Y',strtotime((string)$ev['evaluated_at']))) ?></small></article><?php endforeach; ?><?php if(!$evaluations): ?><div class="card empty-state">No evaluations recorded yet.</div><?php endif; ?></div></section>

      <section class="page-section" id="forecast"><div class="page-title"><div><span class="eyebrow">PLANNING INTELLIGENCE</span><h2>Hiring Forecast</h2><p>Transparent forecast based on current demand and available talent.</p></div></div><div class="forecast-grid"><?php foreach(array_slice($opportunities,0,6) as $o): $related = array_filter($candidates, function($c) use ($o){$req=array_filter(array_map('trim',preg_split('/[,\n]+/',(string)($o['mapped_skills'] ?: $o['required_skills']))?:[]));$have=array_map('mb_strtolower',array_filter(array_map('trim',preg_split('/[,\n]+/',(string)$c['skill_text']))?:[]));$m=0;foreach($req as $r){if(in_array(mb_strtolower($r),$have,true))$m++;}return count($req)?($m/count($req))>=.5:false;}); ?><article class="card forecast-card"><div class="forecast-title"><span class="opp-icon <?= $o['type']==='Internship'?'teal':'blue' ?>"><?= icon($o['type']==='Internship'?'cap':'briefcase') ?></span><div><h3><?= e($o['title']) ?></h3><small><?= e($o['type']) ?></small></div></div><div class="forecast-metrics"><span><b><?= (int)$o['application_count'] ?></b><small>Applications</small></span><span><b><?= count($related) ?></b><small>Potential matches</small></span><span><b><?= round((float)$o['top_match']) ?>%</b><small>Top match</small></span></div><div class="forecast-note">Demand signal: <strong><?= (int)$o['application_count'] > 5 ? 'High activity' : ((int)$o['application_count'] > 0 ? 'Emerging' : 'No application signal') ?></strong>. This is a rule-based MVP forecast, not a fake AI prediction.</div></article><?php endforeach; ?><?php if(!$opportunities): ?><div class="card empty-state">Create opportunities to generate hiring forecasts.</div><?php endif; ?></div></section>

      <section class="page-section" id="analytics"><div class="page-title"><div><span class="eyebrow">DATA INTELLIGENCE</span><h2>Analytics</h2><p>Live metrics calculated from the current database.</p></div></div><div class="analytics-kpis"><div class="card"><small>Candidates</small><b><?= $stats['candidates'] ?></b></div><div class="card"><small>Opportunities</small><b><?= $stats['jobs']+$stats['internships'] ?></b></div><div class="card"><small>Applications</small><b><?= $stats['applications'] ?></b></div><div class="card"><small>Selected</small><b><?= $stats['selected'] ?></b></div><div class="card"><small>Average match</small><b><?= $avgMatch ?>%</b></div></div><div class="analytics-layout"><article class="card chart-card"><div class="card-head"><div><h3>Application Trend</h3><p>Last 14 days</p></div></div><div class="chart tall"><canvas id="analyticsChart"></canvas></div></article><article class="card"><div class="card-head"><div><h3>Skill Demand vs Talent</h3><p>Opportunity demand and candidate availability</p></div></div><div class="skill-table"><?php foreach(array_slice($skills,0,10) as $s): ?><div><span><?= e($s['name']) ?></span><b><?= (int)$s['opportunity_count'] ?></b><b><?= (int)$s['candidate_count'] ?></b></div><?php endforeach; ?><?php if(!$skills): ?><div class="empty-state compact">No skill data available.</div><?php endif; ?></div></article></div></section>

      <section class="page-section" id="messages"><div class="page-title"><div><span class="eyebrow">COMMUNICATION</span><h2>Messages</h2><p>Internal communication records from the portal.</p></div></div><div class="message-list card"><?php foreach($messages as $m): ?><div class="message-row"><div class="avatar">M</div><div><strong><?= e($m['sender_name'] ?? 'User') ?></strong><p><?= e($m['message'] ?? '') ?></p><small><?= e($m['created_at'] ?? '') ?></small></div></div><?php endforeach; ?><?php if(!$messages): ?><div class="empty-state">No messages available.</div><?php endif; ?></div></section>

      <section class="page-section" id="notifications"><div class="page-title"><div><span class="eyebrow">ACTIVITY CENTER</span><h2>Notifications</h2><p>Application, opportunity and collaboration updates.</p></div><?php if($unreadNotifications): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="mark_notifications_read"><button class="btn secondary" type="submit"><?= icon('check',17) ?> Mark all read</button></form><?php endif; ?></div><div class="notification-list card"><?php foreach($notifications as $n): ?><div class="notification-row <?= (int)$n['is_read']===0?'unread':'' ?>"><span class="notification-icon"><?= icon('bell',18) ?></span><div><strong><?= e($n['title'] ?? $n['type'] ?? 'Notification') ?></strong><p><?= e($n['message'] ?? '') ?></p><small><?= e($n['created_at'] ?? '') ?></small></div></div><?php endforeach; ?><?php if(!$notifications): ?><div class="empty-state">No notifications available.</div><?php endif; ?></div></section>
    </div>
  </main>
</div>

<div class="modal" id="opportunityModal"><div class="modal-box wide opportunity-modal-box"><button class="modal-close" data-close type="button">×</button><div class="modal-header"><span class="eyebrow">OPPORTUNITY POSTING</span><h2 id="opportunityModalTitle">Post New Job</h2><p>Create a complete, student-ready job or internship listing. Required fields are marked with <b>*</b>.</p></div><form method="post" id="opportunityForm"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" id="opportunityCsrf"><input type="hidden" name="action" value="save_opportunity"><input type="hidden" name="id" id="oppId"><div class="form-section-title"><span>1</span><div><strong>Opportunity basics</strong><small>What are you hiring for?</small></div></div><div class="form-grid"><label>Opportunity Type *<select class="input" name="type" id="oppType" required><option value="Full-time Job">Full-time Job</option><option value="Internship">Internship</option></select></label><label>Job / Internship Title *<input class="input" name="title" id="oppTitle" required maxlength="180" placeholder="e.g. Software Engineer / Python Intern"></label><label>Department / Function *<input class="input" name="department" id="oppDepartment" required maxlength="120" placeholder="e.g. Engineering, Data Science"></label><label>Location *<input class="input" name="location" id="oppLocation" required maxlength="180" placeholder="e.g. Ahmedabad, Gujarat"></label><label>Work Mode *<select class="input" name="work_mode" id="oppWorkMode" required><option value="In-office">In-office</option><option value="Hybrid">Hybrid</option><option value="Remote">Remote</option></select></label><label>Number of Openings *<input class="input" type="number" min="1" max="10000" name="openings" id="oppOpenings" value="1" required></label></div><div class="form-section-title"><span>2</span><div><strong>Eligibility & skill requirements</strong><small>Define who should apply and what they need</small></div></div><div class="form-grid"><label>Minimum Qualification *<input class="input" name="qualification" id="oppQualification" required maxlength="180" placeholder="e.g. B.E. / B.Tech in IT, CSE or equivalent"></label><label>Minimum CGPA<input class="input" type="number" min="0" max="10" step="0.01" name="min_cgpa" id="oppCgpa" placeholder="e.g. 7.00"></label><label class="full-span">Allowed Branches / Degrees<input class="input" name="allowed_branches" id="oppBranches" maxlength="500" placeholder="e.g. IT, CSE, AI/ML, ECE (comma separated)"></label><label class="full-span">Required Skills *<input class="input" name="required_skills" id="oppSkills" required maxlength="1000" placeholder="Python, SQL, JavaScript, React (comma separated)"></label><label class="job-only">Experience Required *<input class="input" name="experience" id="oppExperience" maxlength="120" placeholder="e.g. 0–2 years"></label><label class="intern-only">Eligibility *<input class="input" name="eligibility" id="oppEligibility" maxlength="500" placeholder="e.g. Final-year students, available 20 hrs/week"></label></div><div class="form-section-title"><span>3</span><div><strong>Compensation & timeline</strong><small>Provide clear application information</small></div></div><div class="form-grid"><label class="job-only">Salary / CTC *<input class="input" name="salary" id="oppSalary" maxlength="180" placeholder="e.g. ₹6–9 LPA"></label><label class="intern-only">Stipend *<input class="input" name="stipend" id="oppStipend" maxlength="180" placeholder="e.g. ₹15,000 / month"></label><label class="intern-only">Internship Duration *<input class="input" name="duration" id="oppDuration" maxlength="120" placeholder="e.g. 6 months"></label><label>Application Deadline<input class="input" type="date" name="deadline" id="oppDeadline"></label><label>Status<select class="input" name="status" id="oppStatus"><option value="Draft">Draft</option><option value="Active">Publish / Active</option><option value="Closed">Closed</option></select></label><label class="full-span">Linked Pre-Placement Assessment <span style="font-weight:400;color:#64748b">(optional)</span><select class="input" name="assessment_id" id="oppAssessment"><option value="">No assessment linked</option><?php foreach($industryAssessments as $ass): ?><option value="<?= (int)$ass['id'] ?>"><?= e($ass['title']) ?><?= $ass['opp_title'] ? ' — '.e($ass['opp_title']) : '' ?></option><?php endforeach; ?></select></label></div><div class="form-section-title"><span>4</span><div><strong>Role details</strong><small>Help students understand the opportunity</small></div></div><label>Role Description *<textarea class="input textarea" name="description" id="oppDescription" rows="5" required maxlength="5000" placeholder="Describe responsibilities, required experience, selection process and expectations..."></textarea></label><label>Learning Outcomes / What the Student Will Gain<textarea class="input textarea" name="learning_outcomes" id="oppLearningOutcomes" rows="4" maxlength="3000" placeholder="e.g. Build production APIs, work with Git, participate in code reviews..."></textarea></label><div class="form-validation-note" id="opportunityValidationNote">Complete all required fields before publishing. You can save an incomplete listing as Draft.</div><div class="modal-actions"><button type="button" class="btn secondary" data-close>Cancel</button><button class="btn primary" type="submit"><?= icon('check',18) ?> Save Opportunity</button></div></form></div></div>

<div class="modal" id="collabModal"><div class="modal-box"><button class="modal-close" data-close>×</button><div class="modal-header"><span class="eyebrow">INDUSTRY ↔ ACADEMIA</span><h2>New Collaboration Request</h2><p>Start a structured partnership with an institute.</p></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="save_collaboration"><label>Institute<select class="input" name="institute" required><?php foreach($institutes as $i): ?><option><?= e($i['name']) ?></option><?php endforeach; ?></select></label><label>Collaboration Type<select class="input" name="type"><option>Workshop</option><option>Campus Drive</option><option>Live Project</option><option>Mentorship</option><option>Research</option><option>FDP</option><option>Industrial Training</option></select></label><label>Focus Skills<input class="input" name="focus_skills" placeholder="Python, AI, Cloud"></label><label>Event Date<input class="input" type="date" name="event_date"></label><label>Description<textarea class="input textarea" name="message" rows="4" placeholder="Describe the proposed collaboration..."></textarea></label><div class="modal-actions"><button type="button" class="btn secondary" data-close>Cancel</button><button class="btn primary" type="submit">Send Request</button></div></form></div></div>

<div class="modal" id="viewModal"><div class="modal-box"><button class="modal-close" data-close>×</button><div id="viewContent"></div></div></div>

<div class="modal" id="learningModal"><div class="modal-box wide"><button class="modal-close" data-close type="button">×</button><div class="modal-header"><span class="eyebrow">SKILL CLOSURE</span><h2 id="learningModalTitle">New Learning Program</h2><p>Publish structured industry training, workshops, certifications or mentorships. Modules are used for student progress tracking.</p></div><form method="post" id="learningProgramForm"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" id="learningCsrf"><input type="hidden" name="action" value="save_learning_program"><input type="hidden" name="id" id="lpId"><div class="form-section-title"><span>1</span><div><strong>Program basics</strong><small>What will students learn?</small></div></div><div class="form-grid"><label>Program Title *<input class="input" name="title" id="lpTitle" required maxlength="180" placeholder="e.g. Python & SQL Boot Camp"></label><label>Program Type *<select class="input" name="type" id="lpType" required><option value="Training">Training</option><option value="Certification">Certification</option><option value="Workshop">Workshop</option><option value="Mentorship">Mentorship</option></select></label><label>Target Skills *<input class="input" name="target_skills" id="lpSkills" required placeholder="Python, SQL, Data Engineering"></label><label>Duration *<input class="input" name="duration" id="lpDuration" required maxlength="100" placeholder="e.g. 4 Weeks / Self-paced"></label><label>Location / Mode *<input class="input" name="location" id="lpLocation" required maxlength="150" placeholder="e.g. Online Live / Hybrid / Ahmedabad"></label><label>Stipend / Fee<input class="input" name="stipend_or_fee" id="lpStipend" maxlength="100" placeholder="e.g. Free / Sponsored / ₹2,000"></label></div><div class="form-section-title"><span>2</span><div><strong>Program details</strong><small>Give students enough information before they join</small></div></div><label>Description *<textarea class="input textarea" name="description" id="lpDescription" rows="4" required placeholder="Describe curriculum, prerequisites, delivery method, benefits and expected outcomes..."></textarea></label><div class="form-section-title"><span>3</span><div><strong>Learning modules</strong><small>Add the modules students will complete. These power student progress tracking.</small></div></div><div id="learningModulesList"></div><button type="button" class="btn secondary" id="addLearningModule">+ Add Module</button><div class="form-grid" style="margin-top:16px;"><label>Status *<select class="input" name="status" id="lpStatus" required><option value="Draft">Draft</option><option value="Active">Active / Published</option><option value="Closed">Closed</option></select></label></div><div class="modal-actions"><button type="button" class="btn secondary" data-close>Cancel</button><button class="btn primary" type="submit"><?= icon('check',18) ?> Save Program</button></div></form></div></div>
<div class="modal" id="resourceModal"><div class="modal-box"><button class="modal-close" data-close>×</button><div class="modal-header"><span class="eyebrow">ACADEMIA KNOWLEDGE EXCHANGE</span><h2>New Industry Resource</h2><p>Publish technical curriculum suggestions, interview topics or question banks for institutes.</p></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="save_industry_resource"><label>Title<input class="input" name="title" required placeholder="e.g. Modern Cloud Native Architecture Question Bank"></label><label>Topic<input class="input" name="topic" required placeholder="e.g. Cloud Computing & Microservices"></label><label>Target Audience<select class="input" name="audience"><option value="Faculty">Faculty</option><option value="Institute">Institute</option><option value="Both">Both</option></select></label><label>Resource / Reference URL<input class="input" type="url" name="resource_url" placeholder="https://..."></label><label>Description & Recommendations<textarea class="input textarea" name="description" rows="5" placeholder="Details on topics, industry standards, key question areas..."></textarea></label><div class="modal-actions"><button type="button" class="btn secondary" data-close>Cancel</button><button class="btn primary" type="submit"><?= icon('check',18) ?> Publish Resource</button></div></form></div></div>

<div class="modal" id="assessmentModal" style="display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;">
  <div class="modal-box" style="background:#fff; border-radius:16px; padding:24px; max-width:540px; width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0; font-size:18px;">Create Pre-Placement Assessment</h3>
      <button onclick="document.getElementById('assessmentModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">×</button>
    </div>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="save_assessment">
      <label>Assessment Title *<input class="input" name="title" required placeholder="e.g. Python & SQL Developer Screening"></label>
      <label>Link to Opportunity (Job / Internship)
        <select class="input" name="opportunity_id">
          <option value="">-- Optional: General Assessment --</option>
          <?php foreach($opportunities as $o): ?>
            <option value="<?= (int)$o['id'] ?>"><?= e($o['title'].' • '.$o['type']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <label>Duration (Minutes) *<input class="input" type="number" name="duration_mins" value="30" min="5" max="180" required></label>
        <label>Passing Score (%) *<input class="input" type="number" step="0.1" name="passing_score_pct" value="70" min="1" max="100" required></label>
      </div>
      <label>Instructions & Description<textarea class="input textarea" name="description" rows="3" placeholder="Explain test rules, time limit, and topics covered..."></textarea></label>
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
        <button type="button" class="btn secondary" onclick="document.getElementById('assessmentModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn primary">Create Assessment</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="addQuestionModal" style="display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;">
  <div class="modal-box" style="background:#fff; border-radius:16px; padding:24px; max-width:600px; width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0; font-size:18px;" id="addQuestionModalTitle">Add Question to Assessment</h3>
      <button onclick="document.getElementById('addQuestionModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">×</button>
    </div>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="add_assessment_question">
      <input type="hidden" name="assessment_id" id="qAssessmentId">
      <label>Question Text *<textarea class="input textarea" name="question" rows="2" required placeholder="Enter multiple-choice question text..."></textarea></label>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <label>Option A *<input class="input" name="option_a" required></label>
        <label>Option B *<input class="input" name="option_b" required></label>
        <label>Option C<input class="input" name="option_c"></label>
        <label>Option D<input class="input" name="option_d"></label>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:8px;">
        <label>Correct Answer *
          <select class="input" name="correct_option" required>
            <option value="A">Option A</option>
            <option value="B">Option B</option>
            <option value="C">Option C</option>
            <option value="D">Option D</option>
          </select>
        </label>
        <label>Marks<input class="input" type="number" name="marks" value="1" min="1"></label>
        <label>Difficulty
          <select class="input" name="difficulty">
            <option value="Easy">Easy</option>
            <option value="Medium" selected>Medium</option>
            <option value="Hard">Hard</option>
          </select>
        </label>
      </div>
      <label style="margin-top:8px;">Topic / Skill Tag<input class="input" name="skill_topic" placeholder="e.g. Python, SQL, Git"></label>
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
        <button type="button" class="btn secondary" onclick="document.getElementById('addQuestionModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn primary">Save Question</button>
      </div>
    </form>
  </div>
</div>

<div class="modal" id="workshopModal" style="display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999;">
  <div class="modal-box" style="background:#fff; border-radius:16px; padding:24px; max-width:620px; width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="margin:0; font-size:18px;">Schedule Campus Workshop / Webinar</h3>
      <button onclick="document.getElementById('workshopModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">×</button>
    </div>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="action" value="save_workshop">
      <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
        <label>Workshop Title *<input class="input" name="title" required placeholder="e.g. AI & Machine Learning Masterclass"></label>
        <label>Type *
          <select class="input" name="type" required>
            <option value="Technical Workshop">Technical Workshop</option>
            <option value="Webinar">Webinar</option>
            <option value="Guest Lecture">Guest Lecture</option>
            <option value="Industry Talk">Industry Talk</option>
            <option value="Masterclass">Masterclass</option>
            <option value="Faculty Development">Faculty Development (FDP)</option>
            <option value="Career Session">Career Session</option>
          </select>
        </label>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <label>Start Date & Time *<input class="input" type="datetime-local" name="start_time" required></label>
        <label>End Date & Time *<input class="input" type="datetime-local" name="end_time" required></label>
      </div>
      <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
        <label>Venue / Meeting Link *<input class="input" name="venue_or_link" required placeholder="e.g. https://meet.google.com/... or Campus Auditorium"></label>
        <label>Max Capacity *<input class="input" type="number" name="max_capacity" value="100" min="1"></label>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <label>Speaker Name<input class="input" name="speaker_name" placeholder="e.g. Vikram Malhotra"></label>
        <label>Speaker Designation<input class="input" name="speaker_designation" placeholder="e.g. Lead Architect"></label>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
        <label>Target Audience<input class="input" name="target_audience" placeholder="e.g. B.Tech IT 3rd/4th Year"></label>
        <label>Target Institute
          <select class="input" name="target_institute_id">
            <option value="">-- All Partner Institutes --</option>
            <?php foreach($institutes as $inst): ?>
              <option value="<?= (int)$inst['id'] ?>"><?= e($inst['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Required Skills / Focus<input class="input" name="required_skills" placeholder="Python, TensorFlow, Cloud"></label>
      <label>Description & Learning Outcomes<textarea class="input textarea" name="description" rows="3" placeholder="Outline agenda, topics, and key takeaways..."></textarea></label>
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
        <button type="button" class="btn secondary" onclick="document.getElementById('workshopModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn primary">Publish Workshop</button>
      </div>
    </form>
  </div>
</div>

<script>
window.skillBridge = {
 csrfToken: <?= json_for_js($csrfToken) ?>,
 company: <?= json_for_js($company) ?>,
 stats: <?= json_for_js($stats) ?>,
 avgMatch: <?= (int)$avgMatch ?>,
 candidates: <?= json_for_js($candidates) ?>,
 opportunities: <?= json_for_js($opportunities) ?>,
 learningPrograms: <?= json_for_js($learningPrograms) ?>,
 applications: <?= json_for_js($applications) ?>,
 trend: <?= json_for_js($trend) ?>,
 skills: <?= json_for_js($skills) ?>,
 institutes: <?= json_for_js($institutes) ?>,
 industryAssessments: <?= json_for_js($industryAssessments) ?>,
 workshops: <?= json_for_js($workshops) ?>
};

function openAddQuestionModal(assId, title) {
  document.getElementById('qAssessmentId').value = assId;
  document.getElementById('addQuestionModalTitle').innerText = 'Add Question to: ' + title;
  document.getElementById('addQuestionModal').style.display = 'flex';
}
</script>
<script src="script.js"></script>
</body>
</html>
