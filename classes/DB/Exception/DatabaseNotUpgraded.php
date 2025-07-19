<?php

namespace Blink\DB\Exception;

use Blink\Exception\BaseException;

class DatabaseNotUpgraded extends BaseException {
	public function __construct(int $version, int $target) {
		parent::__construct(DATABASE_NOT_UPGRADED, "Cơ sở dữ liệu chưa được cập nhật! Phiên bản hiện tại là {$version} nhưng phiên bản của cấu trúc bảng là {$target}. Vui lòng cập nhật lại đoạn mã nâng cấp của bạn!", 500);
	}
}
