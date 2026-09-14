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

	"errorpage.etherInvalidMiddleware.title" => "Tham chiếu middleware không hợp lệ",
	"errorpage.etherInvalidMiddleware.content" => "<b>{reference}</b> không phải là một class middleware, một alias đã đăng ký hay một group. "
		. "Đăng ký nó bằng <code>Ether::register()</code> trong <code>attach()</code> của module, hoặc kiểm tra lại chính tả.",

	"errorpage.essenceBindingNotDefined.title" => "Chưa có binding cho abstract này",
	"errorpage.essenceBindingNotDefined.content" => "<b>{abstract}</b> là một interface hoặc abstract class, nên Essence không thể tự khởi tạo nó. "
		. "Gắn nó với một class cụ thể bằng <code>Essence::bind()</code> trong <code>define()</code> của module.",

	"errorpage.noUserClass.title" => "Chưa đăng ký class người dùng",
	"errorpage.noUserClass.content" => "Có thứ gì đó cần người dùng hiện tại, nhưng chưa module nào khai báo class đại diện cho người dùng. "
		. "Tạo một class kế thừa <code>Blink\\Model\\UserBase</code> và đăng ký nó bằng "
		. "<code>Blink::useUserClass()</code> trong <code>define()</code> của module.",

	"trace.level.debug" => "Gỡ lỗi",
	"trace.level.info" => "Thông tin",
	"trace.level.notice" => "Lưu ý",
	"trace.level.warning" => "Cảnh báo",
	"trace.level.error" => "Lỗi",
	"trace.level.critical" => "Nghiêm trọng",
	"trace.level.alert" => "Báo động",
	"trace.level.emergency" => "Khẩn cấp",

	"trace.mode.view" => "Xem",
	"trace.mode.create" => "Tạo",
	"trace.mode.update" => "Cập nhật",
	"trace.mode.delete" => "Xóa",
	"trace.mode.report" => "Báo cáo",

	"trace.actor.system" => "hệ thống",
	"trace.model.created" => "{model} #{id} đã được tạo bởi {actor}.",
	"trace.model.updated" => "{model} #{id} đã được cập nhật bởi {actor}, {changeCount} trường thay đổi.",
	"trace.model.deleted" => "{model} #{id} đã bị xóa bởi {actor}.",

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
