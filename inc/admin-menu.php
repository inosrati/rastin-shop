<?php
/**
 * منوی مدیریت قالب Rastin Shop.
 *
 * یک منوی سطح‌بالای واحد در پیشخوان می‌سازد و یک «نقطه‌ی اتصال» برای افزونه‌های
 * همراه فراهم می‌کند تا صفحاتشان را به‌جای منوی مجزا، زیرِ همین منو ثبت کنند.
 *
 * افزونه‌ها می‌توانند به دو روش زیرمنو اضافه کنند:
 *   1) استفاده از ثابت RASTIN_ADMIN_MENU_SLUG به‌عنوان والدِ add_submenu_page().
 *   2) هوک‌شدن به اکشن 'rastin_admin_menu' که اسلاگ والد را پاس می‌دهد.
 *
 * برای اطمینان از اینکه منوی قالب قبل از افزونه‌ها ثبت شده، افزونه‌ها باید
 * روی 'admin_menu' با اولویت بزرگ‌تر از ۱۰ (مثلاً ۲۰) زیرمنو اضافه کنند.
 *
 * @package Rastin
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * اسلاگ منوی سطح‌بالای قالب؛ افزونه‌ها از این به‌عنوان والد استفاده می‌کنند.
 */
if ( ! defined( 'RASTIN_ADMIN_MENU_SLUG' ) ) {
	define( 'RASTIN_ADMIN_MENU_SLUG', 'rastin-dashboard' );
}

if ( ! function_exists( 'rastin_register_admin_menu' ) ) {
	/**
	 * ثبت منوی سطح‌بالای قالب و زیرمنوهای اختصاصی آن.
	 *
	 * @return void
	 */
	function rastin_register_admin_menu() {
		add_menu_page(
			__( 'قالب رستین', 'rastin' ),
			__( 'قالب رستین', 'rastin' ),
			'edit_theme_options',
			RASTIN_ADMIN_MENU_SLUG,
			'rastin_render_dashboard_page',
			'dashicons-store',
			59
		);

		add_submenu_page(
			RASTIN_ADMIN_MENU_SLUG,
			__( 'پیشخوان قالب', 'rastin' ),
			__( 'پیشخوان', 'rastin' ),
			'edit_theme_options',
			RASTIN_ADMIN_MENU_SLUG,
			'rastin_render_dashboard_page'
		);

		/**
		 * به افزونه‌های همراه اجازه می‌دهد صفحاتشان را زیرِ منوی قالب ثبت کنند.
		 *
		 * @param string $parent_slug اسلاگ منوی والد قالب.
		 */
		do_action( 'rastin_admin_menu', RASTIN_ADMIN_MENU_SLUG );
	}
}
add_action( 'admin_menu', 'rastin_register_admin_menu' );

if ( ! function_exists( 'rastin_render_dashboard_page' ) ) {
	/**
	 * صفحه‌ی پیشخوان قالب: خوش‌آمد، لینک‌های سریع و افزونه‌های متصل‌شده.
	 *
	 * @return void
	 */
	function rastin_render_dashboard_page() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$theme = wp_get_theme();
		?>
		<div class="wrap" dir="rtl">
			<h1><?php esc_html_e( 'قالب رستین', 'rastin' ); ?></h1>
			<p style="max-width:640px;">
				<?php
				printf(
					/* translators: %s: شماره نسخه قالب */
					esc_html__( 'به پیشخوان قالب فروشگاهی رستین خوش آمدید (نسخه %s). از این‌جا به تنظیمات قالب و افزونه‌های همراه دسترسی دارید.', 'rastin' ),
					esc_html( $theme->get( 'Version' ) )
				);
				?>
			</p>

			<h2><?php esc_html_e( 'دسترسی سریع', 'rastin' ); ?></h2>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'site-editor.php' ) ); ?>">
					<?php esc_html_e( 'ویرایشگر سایت', 'rastin' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'site-editor.php?path=%2Fwp_template' ) ); ?>">
					<?php esc_html_e( 'قالب‌ها (Templates)', 'rastin' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=gutenberg-edit-site' ) ); ?>">
					<?php esc_html_e( 'سبک‌ها و رنگ‌ها', 'rastin' ); ?>
				</a>
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin' ) ); ?>">
						<?php esc_html_e( 'ووکامرس', 'rastin' ); ?>
					</a>
				<?php endif; ?>
			</p>

			<h2><?php esc_html_e( 'افزونه‌های همراه', 'rastin' ); ?></h2>
			<p style="max-width:640px;">
				<?php esc_html_e( 'صفحات تنظیماتِ افزونه‌های سازگار با رستین در همین منو (سمت راست) زیرِ «قالب رستین» نمایش داده می‌شوند.', 'rastin' ); ?>
			</p>
			<?php
			/**
			 * به افزونه‌ها اجازه می‌دهد محتوا/کارت خود را در صفحه‌ی پیشخوان قالب نمایش دهند.
			 */
			do_action( 'rastin_dashboard_content' );
			?>
		</div>
		<?php
	}
}
