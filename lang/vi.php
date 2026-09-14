<?php
/**
 * Language strings for vietnamese.
 *
 * @author		Belikhun
 * @since		1.0.0
 * @license		https://tldrlegal.com/license/mit-license MIT
 *
 * Copyright (C) 2018-2026 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

return array(
	"errorpage.openFile" => "Mở File",

	"errorpage.classNotDefined.title" => "Class không tồn tại",
	"errorpage.classNotDefined.content" => "Tệp <b>{file}</b> đã được nạp, nhưng không định nghĩa class <b>{class}</b>. "
		. "Kiểm tra lại chính tả hoặc thêm class này nếu bạn chưa định nghĩa nó.",

	"errorpage.classNotFound.title" => "Không tìm thấy class",
	"errorpage.classNotFound.content" => "Không nạp được class <b>{class}</b>. Autoload đã thử các đường dẫn sau:",

	"errorpage.routeNotFound.title" => "Đường dẫn không tồn tại",
	"errorpage.routeNotFound.content" => "Có vẻ như đường dẫn này không còn tồn tại trên máy chủ. "
		. "Liên hệ quản trị viên nếu bạn thấy đây là lỗi.",

	"errorpage.invalidDefinition.title" => "Định nghĩa class không hợp lệ",
	"errorpage.invalidDefinition.content" => "Class <b>{class}</b> của bạn phải kế thừa <b>{from}</b>.",

	"errorpage.moduleNotFound.title" => "Không tìm thấy module",
	"errorpage.moduleNotFound.content" => "<b>{module}</b> được khai báo trong <code>CONFIG::\$MODULES</code> nhưng "
		. "không nạp được. Kiểm tra tên class và vị trí tệp của nó.",

	"errorpage.notLoggedIn.title" => "Bạn chưa đăng nhập",
	"errorpage.notLoggedIn.content" => "Trang này yêu cầu một phiên đã xác thực. Hãy đăng nhập và thử lại.",

	"time.now" => "mới đây",
	"time.ago" => "{value} {unit} trước",
	"time.in" => "{value} {unit} sau",
	"time.day.next" => "ngày mai",
	"time.day.last" => "hôm qua",
	"time.year.next" => "năm sau",
	"time.year.last" => "năm ngoái",
	"time.unit.year" => "năm",
	"time.unit.year.one" => "năm",
	"time.unit.day" => "ngày",
	"time.unit.day.one" => "ngày",
	"time.unit.hour" => "giờ",
	"time.unit.hour.one" => "giờ",
	"time.unit.minute" => "phút",
	"time.unit.minute.one" => "phút",
	"time.unit.second" => "giây",
	"time.unit.second.one" => "giây",

	"terra.notUpgraded" => "Cấu trúc cơ sở dữ liệu chưa được cập nhật: mới áp dụng {applied} trên {total} lớp.",
	"terra.notUpgraded.details" => "Chạy <code>php core/blink terra:apply</code>, hoặc đợi tiến trình đang áp dụng chúng hoàn tất."
);
