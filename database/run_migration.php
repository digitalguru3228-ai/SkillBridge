<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';

try {
    $pdo = db();

    // 1. Upgrade learning_enrollments status enum and add withdrawn_at if missing
    $pdo->exec("ALTER TABLE learning_enrollments MODIFY COLUMN status ENUM('Enrolled','In Progress','Completed','Withdrawn') NOT NULL DEFAULT 'Enrolled'");
    if (!columnExists($pdo, 'learning_enrollments', 'withdrawn_at')) {
        $pdo->exec("ALTER TABLE learning_enrollments ADD COLUMN withdrawn_at TIMESTAMP NULL AFTER enrolled_at");
    }

    // 2. Upgrade applications status enum and add withdrawn_at if missing
    $pdo->exec("ALTER TABLE applications MODIFY COLUMN status ENUM('Applied','Under Review','Shortlisted','Interview','Selected','Rejected','Withdrawn') NOT NULL DEFAULT 'Applied'");
    if (!columnExists($pdo, 'applications', 'withdrawn_at')) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN withdrawn_at TIMESTAMP NULL AFTER applied_at");
    }

    // 3. Seed assessment questions
    $questions = [
        ['Data Structures', 'Which data structure follows the LIFO (Last In First Out) principle?', 'Queue', 'Stack', 'Linked List', 'Tree', 'B', 'Easy', 'Stack follows Last In First Out (LIFO).'],
        ['Data Structures', 'What is the average time complexity for searching an element in a Balanced Binary Search Tree (AVL)?', 'O(1)', 'O(n)', 'O(log n)', 'O(n^2)', 'C', 'Medium', 'Balanced BSTs maintain O(log n) height, making search operation O(log n).'],
        ['Data Structures', 'In Graph Theory, which algorithm finds the shortest path from a single source vertex to all other vertices in a weighted graph with non-negative weights?', 'Kruskal Algorithm', 'Dijkstra Algorithm', 'Prim Algorithm', 'Floyd-Warshall Algorithm', 'B', 'Hard', 'Dijkstra algorithm is designed for single-source shortest paths on non-negatively weighted graphs.'],

        ['Java', 'Which keyword is used to prevent method overriding in Java?', 'static', 'abstract', 'final', 'synchronized', 'C', 'Easy', 'The final keyword on a method prevents subclasses from overriding it.'],
        ['Java', 'What is the garbage collection algorithm basis in modern JVMs for heap memory management?', 'Reference Counting', 'Mark-Sweep-Compact / Generational GC', 'Manual malloc/free', 'Stack allocation only', 'B', 'Medium', 'JVMs use generational garbage collection (Young/Old gen) with Mark-Sweep-Compact phases.'],
        ['Java', 'What occurs when calling `wait()` inside a non-synchronized block in Java?', 'Deadlock occurs immediately', 'Throws IllegalMonitorStateException', 'Thread yields CPU execution to next process', 'Compiles with warning only', 'B', 'Hard', 'Calling wait() without holding the monitor lock throws IllegalMonitorStateException.'],

        ['Python', 'What does the `*args` syntax in a Python function parameter list represent?', 'Variable number of keyword arguments', 'Variable number of non-keyword positional arguments', 'A pointer to a memory address', 'A mandatory list parameter', 'B', 'Easy', '*args collects excess positional arguments into a tuple.'],
        ['Python', 'Which decorator is used in Python to define a method that belongs to the class rather than instances?', '@staticmethod', '@classmethod', '@property', '@abstractmethod', 'B', 'Medium', '@classmethod passes the class (cls) as the first argument.'],

        ['SQL', 'Which constraint guarantees that all values in a column are distinct?', 'FOREIGN KEY', 'CHECK', 'UNIQUE', 'DEFAULT', 'C', 'Easy', 'UNIQUE constraint ensures all values in a column are unique across table rows.'],
        ['SQL', 'What is the main function of an ACID-compliant database transaction?', 'To compress table data storage', 'To guarantee Atomicity, Consistency, Isolation, and Durability', 'To encrypt SQL query strings', 'To format output HTML tables', 'B', 'Medium', 'ACID guarantees reliable execution of database transactions.']
    ];

    $insStmt = $pdo->prepare("INSERT INTO assessment_questions (topic, question, option_a, option_b, option_c, option_d, correct_option, difficulty, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE question=VALUES(question)");
    foreach ($questions as $q) {
        $insStmt->execute($q);
    }

    // 4. Run Migration 005 SQL script
    $sql005 = file_get_contents(__DIR__ . '/005_production_ready_enhancements.sql');
    if ($sql005) {
        $pdo->exec($sql005);
    }

    // 5. Run Migration 008: industry assessment response storage
    $sql008 = file_get_contents(__DIR__ . '/008_industry_assessment_runtime.sql');
    if ($sql008) {
        $pdo->exec($sql008);
    }

    // 6. Run Migration 009: real account / password / Google identity fields
    $sql009 = file_get_contents(__DIR__ . '/009_account_authentication.sql');
    if ($sql009) {
        $pdo->exec($sql009);
    }

    // 7. Run Migration 010: in-app notifications, messages and email audit log
    $sql010 = file_get_contents(__DIR__ . '/010_notifications_messages.sql');
    if ($sql010) {
        $pdo->exec($sql010);
    }


    // 8. Normalize activity tables for existing SKILLBRIDGE databases.
    // Older builds used company_id/notification_type/sender_name/message while
    // the normalized build uses user_id/type/sender_user_id/body. Keep both.
    $activityTables = ['notifications','messages','email_logs'];
    foreach ($activityTables as $t) {
        if (!tableExists($pdo, $t)) {
            throw new RuntimeException("Required activity table {$t} does not exist after migration 010.");
        }
    }

    $ensureColumn = static function(PDO $pdo, string $table, string $column, string $definition): void {
        if (!columnExists($pdo,$table,$column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };

    $ensureColumn($pdo,'notifications','user_id','INT UNSIGNED NULL');
    $ensureColumn($pdo,'notifications','type',"VARCHAR(40) NULL DEFAULT 'system'");
    $ensureColumn($pdo,'notifications','notification_type',"VARCHAR(40) NULL");
    $ensureColumn($pdo,'notifications','company_id','INT UNSIGNED NULL');
    $ensureColumn($pdo,'notifications','title','VARCHAR(180) NULL');
    $ensureColumn($pdo,'notifications','message','TEXT NULL');
    $ensureColumn($pdo,'notifications','entity_type','VARCHAR(60) NULL');
    $ensureColumn($pdo,'notifications','entity_id','INT UNSIGNED NULL');
    $ensureColumn($pdo,'notifications','is_read','TINYINT(1) NOT NULL DEFAULT 0');
    $ensureColumn($pdo,'notifications','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN user_id INT UNSIGNED NULL");
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN company_id INT UNSIGNED NULL");
    $pdo->exec("UPDATE notifications SET type=COALESCE(NULLIF(type,''),notification_type,'system') WHERE type IS NULL OR type=''");
    $pdo->exec("UPDATE notifications SET notification_type=COALESCE(NULLIF(notification_type,''),type,'system') WHERE notification_type IS NULL OR notification_type=''");
    // Existing company notifications become visible to that company's active industry user.
    if (columnExists($pdo,'notifications','company_id')) {
        $pdo->exec("UPDATE notifications n JOIN users u ON u.company_id=n.company_id AND u.role='industry' AND u.status='Active' SET n.user_id=u.id WHERE n.user_id IS NULL AND n.company_id IS NOT NULL");
    }

    $ensureColumn($pdo,'messages','sender_user_id','INT UNSIGNED NULL');
    $ensureColumn($pdo,'messages','recipient_user_id','INT UNSIGNED NULL');
    $ensureColumn($pdo,'messages','company_id','INT UNSIGNED NULL');
    $ensureColumn($pdo,'messages','sender_name','VARCHAR(150) NULL');
    $ensureColumn($pdo,'messages','recipient_name','VARCHAR(150) NULL');
    $ensureColumn($pdo,'messages','subject','VARCHAR(180) NULL');
    $ensureColumn($pdo,'messages','body','TEXT NULL');
    $ensureColumn($pdo,'messages','message','TEXT NULL');
    $ensureColumn($pdo,'messages','is_read','TINYINT(1) NOT NULL DEFAULT 0');
    $ensureColumn($pdo,'messages','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    $pdo->exec("ALTER TABLE messages MODIFY COLUMN company_id INT UNSIGNED NULL");
    $pdo->exec("ALTER TABLE messages MODIFY COLUMN sender_name VARCHAR(150) NULL");
    $pdo->exec("ALTER TABLE messages MODIFY COLUMN recipient_name VARCHAR(150) NULL");
    $pdo->exec("ALTER TABLE messages MODIFY COLUMN subject VARCHAR(180) NULL");
    $pdo->exec("ALTER TABLE messages MODIFY COLUMN body TEXT NULL");
    $pdo->exec("ALTER TABLE messages MODIFY COLUMN message TEXT NULL");
    $pdo->exec("UPDATE messages SET body=COALESCE(body,message) WHERE body IS NULL AND message IS NOT NULL");
    $pdo->exec("UPDATE messages SET message=COALESCE(message,body) WHERE message IS NULL AND body IS NOT NULL");
    // Backfill legacy company messages to the company's active industry account.
    $pdo->exec("UPDATE messages m JOIN users u ON u.company_id=m.company_id AND u.role='industry' AND u.status='Active' SET m.recipient_user_id=u.id WHERE m.recipient_user_id IS NULL AND m.company_id IS NOT NULL");

    $ensureColumn($pdo,'email_logs','user_id','INT UNSIGNED NULL');
    $ensureColumn($pdo,'email_logs','recipient_email','VARCHAR(190) NULL');
    $ensureColumn($pdo,'email_logs','subject','VARCHAR(255) NULL');
    $ensureColumn($pdo,'email_logs','status',"VARCHAR(20) NULL");
    $ensureColumn($pdo,'email_logs','error_message','TEXT NULL');
    $ensureColumn($pdo,'email_logs','created_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');

    // Helpful indexes are added only when absent.
    $idxExists = static function(PDO $pdo,string $table,string $index):bool {
        $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?");
        $q->execute([$table,$index]); return (int)$q->fetchColumn()>0;
    };
    if (!$idxExists($pdo,'notifications','idx_notifications_user')) {
        $pdo->exec("CREATE INDEX idx_notifications_user ON notifications(user_id,is_read,created_at)");
    }
    if (!$idxExists($pdo,'messages','idx_messages_recipient')) {
        $pdo->exec("CREATE INDEX idx_messages_recipient ON messages(recipient_user_id,is_read,created_at)");
    }
    if (!$idxExists($pdo,'email_logs','idx_email_logs_user')) {
        $pdo->exec("CREATE INDEX idx_email_logs_user ON email_logs(user_id,created_at)");
    }

    echo "SUCCESS: Student portal, assessment runtime, account authentication, and activity notifications are applied successfully!\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
