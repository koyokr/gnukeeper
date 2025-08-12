<?php
/**
 * GnuKeeper 정규식 기반 스팸 필터
 */

if (!defined('_GNUBOARD_')) exit;

class GK_RegexFilter {

    /**
     * 콘텐츠 검사
     */
    public static function check($board, $wr_id) {
        global $write, $wr_subject, $wr_content, $wr_name, $wr_email;
        $is_blocking_enabled = GK_Common::get_config('regex_spam_enabled') == '1';

        // 검사 대상 설정
        $check_title = GK_Common::get_config('regex_spam_check_title') == '1';
        $check_content = GK_Common::get_config('regex_spam_check_content') == '1';
        $check_name = GK_Common::get_config('regex_spam_check_name') == '1';
        $check_email = GK_Common::get_config('regex_spam_check_email') == '1';

        // 검사할 텍스트 준비
        $texts_to_check = array();
        if ($check_title && isset($wr_subject)) {
            $texts_to_check['title'] = $wr_subject;
        }
        if ($check_content && isset($wr_content)) {
            $texts_to_check['content'] = $wr_content;
        }
        if ($check_name && isset($wr_name)) {
            $texts_to_check['name'] = $wr_name;
        }
        if ($check_email && isset($wr_email)) {
            $texts_to_check['email'] = $wr_email;
        }

        // 활성화된 정규식 규칙 가져오기
        $sql = "SELECT * FROM " . GK_SECURITY_REGEX_SPAM_TABLE . "
                WHERE srs_enabled = 1
                ORDER BY srs_priority ASC";

        $result = sql_query($sql);
        if (!$result) {
            return true;
        }

        while ($rule = sql_fetch_array($result)) {
            $targets = explode(',', $rule['srs_target']);

            foreach ($texts_to_check as $target => $text) {
                if (!in_array($target, $targets)) {
                    continue;
                }

                // 정규식 매칭
                $pattern = '/' . $rule['srs_pattern'] . '/';
                if (!$rule['srs_case_sensitive']) {
                    $pattern .= 'i';
                }

                if (@preg_match($pattern, $text, $matches)) {
                    // 항상 스팸 탐지 로그 기록 (OFF 상태에서도)
                    self::log_detection($rule, $text, $matches[0] ?? '', $board, $wr_id);

                    // 매칭 횟수 증가
                    self::increment_hit_count($rule['srs_id']);

                    if ($is_blocking_enabled) {
                        // ON 상태에서만 차단 처리
                        // 자동 IP 차단 (설정에 따라)
                        if (GK_Common::get_config('regex_spam_auto_block') == '1') {
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $reason = '정규식 스팸 탐지: ' . $rule['srs_name'];
                            GK_SpamDetector::auto_block_ip($ip, 'auto_regex', $reason);
                        }

                        // 액션 처리
                        return self::handle_action($rule['srs_action']);
                    }
                }
            }
        }

        return true;
    }

    /**
     * 탐지 로그 기록
     */
    private static function log_detection($rule, $full_text, $matched_text, $board, $wr_id) {
        global $member;

        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $mb_id = $member['mb_id'] ?? '';

        $sql = "INSERT INTO " . GK_SECURITY_REGEX_SPAM_LOG_TABLE . "
                (srsl_srs_id, srsl_ip, srsl_mb_id, srsl_target_type, srsl_bo_table, srsl_wr_id,
                 srsl_matched_text, srsl_full_content, srsl_action_taken, srsl_user_agent, srsl_datetime)
                VALUES (
                    " . (int)$rule['srs_id'] . ",
                    '" . sql_escape_string($ip) . "',
                    '" . sql_escape_string($mb_id) . "',
                    'board_write',
                    '" . sql_escape_string($board['bo_table']) . "',
                    " . (int)$wr_id . ",
                    '" . sql_escape_string($matched_text) . "',
                    '" . sql_escape_string($full_text) . "',
                    '" . sql_escape_string($rule['srs_action']) . "',
                    '" . sql_escape_string($user_agent) . "',
                    NOW()
                )";

        sql_query($sql);
    }

    /**
     * 매칭 횟수 증가
     */
    private static function increment_hit_count($rule_id) {
        $sql = "UPDATE " . GK_SECURITY_REGEX_SPAM_TABLE . "
                SET srs_hit_count = srs_hit_count + 1,
                    srs_update_datetime = NOW()
                WHERE srs_id = " . (int)$rule_id;

        sql_query($sql);
    }

    /**
     * 액션 처리
     */
    private static function handle_action($action) {
        switch ($action) {
            case 'block':
                alert('스팸으로 의심되는 내용이 포함되어 있습니다.');
                return false;

            case 'ghost':
                // 유령 모드 - 작성자에게만 보이도록 설정
                global $wr_option;
                $wr_option = 'secret';
                return true;

            case 'delete':
                // 삭제는 별도 처리 필요
                return false;

            default:
                return true;
        }
    }
}