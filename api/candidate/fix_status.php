<?php
// api/candidate/fix_status.php
require_once '../config/db.php';

try {
    $pdo->beginTransaction();

    $stmtCand = $pdo->query("SELECT ec.id, e.profileKey FROM evaluation_candidates ec INNER JOIN evaluations e ON ec.evaluationId = e.id WHERE ec.status = 'IN_PROGRESS'");
    $candidates = $stmtCand->fetchAll(PDO::FETCH_ASSOC);

    $fixedCount = 0;

    foreach ($candidates as $cand) {
        $evaluationCandidateId = $cand['id'];
        $profileKey = $cand['profileKey'];

        $testsAssignedKeys = [];
        if (!empty($profileKey)) {
            $stmtPivot = $pdo->prepare("SELECT ct.`key` FROM profile_tests pt INNER JOIN catalog_tests ct ON pt.testId = ct.id WHERE pt.profileId = ?");
            $stmtPivot->execute([$profileKey]);
            $testsAssignedKeys = $stmtPivot->fetchAll(PDO::FETCH_COLUMN);

            if (empty($testsAssignedKeys)) {
                $stmtProf = $pdo->prepare("SELECT testKeys FROM profiles WHERE id = ?");
                $stmtProf->execute([$profileKey]);
                $profJson = $stmtProf->fetchColumn();
                if (!empty($profJson)) {
                    $keysArr = json_decode($profJson, true);
                    if (is_array($keysArr)) {
                        $testsAssignedKeys = $keysArr;
                    }
                }
            }
        }

        if (!empty($testsAssignedKeys)) {
            $stmtDone = $pdo->prepare("SELECT DISTINCT testKey FROM candidate_answers WHERE evaluationCandidateId = ?");
            $stmtDone->execute([$evaluationCandidateId]);
            $doneKeys = $stmtDone->fetchAll(PDO::FETCH_COLUMN);

            $allDone = true;
            foreach ($testsAssignedKeys as $tk) {
                if (!in_array($tk, $doneKeys)) {
                    $allDone = false;
                    break;
                }
            }

            if ($allDone && count($testsAssignedKeys) > 0) {
                $stmtComplete = $pdo->prepare("UPDATE evaluation_candidates SET status = 'COMPLETED', completedAt = NOW() WHERE id = ?");
                $stmtComplete->execute([$evaluationCandidateId]);
                $fixedCount++;
            }
        }
    }

    $pdo->commit();
    echo "Proceso finalizado. Postulantes corregidos a COMPLETED: " . $fixedCount;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?>
