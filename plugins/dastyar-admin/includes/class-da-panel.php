<?php
/**
 * پنل مستقل کارمندان — کدکوتاه [dastyar_staff_panel].
 * همان ۵۰ ماژول کنسول مرکز را — بدون بازنویسی — از داخل یک صفحه‌ی عادی سایت (نه
 * پیشخوان وردپرس) نشان می‌دهد. دسترسی: هر کاربر واردشده‌ای که قابلیت
 * manage_woocommerce داشته باشد (نقش «کارمند دستیار» یا مدیر واقعی سایت).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DA_Panel {

	const LOGIN_NONCE = 'da_panel_login';

	public function boot() {
		add_shortcode( 'dastyar_staff_panel', array( $this, 'render' ) );
		add_action( 'admin_post_da_staff_save', array( 'DA_Page_Staff', 'save' ) );
		add_action( 'admin_post_nopriv_da_staff_save', array( 'DA_Page_Staff', 'save' ) );
	}

	public function render() {
		if ( ! is_user_logged_in() ) {
			return $this->login_screen();
		}
		if ( ! DA_Staff::current_user_has_access() ) {
			return $this->denied_screen();
		}
		return $this->panel_screen();
	}

	/* ------------------------------------------------------------------
	 * صفحه ورود (بدون نیاز به پیشخوان وردپرس)
	 * ---------------------------------------------------------------- */

	protected function login_screen() {
		$err = '';
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['da_login_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_key( $_POST['da_login_nonce'] ), self::LOGIN_NONCE ) ) {
				$err = 'نشست نامعتبر است؛ دوباره تلاش کنید.';
			} else {
				$creds = array(
					'user_login'    => sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
					'user_password' => (string) ( $_POST['pwd'] ?? '' ),
					'remember'      => true,
				);
				$user = wp_signon( $creds, is_ssl() );
				if ( is_wp_error( $user ) ) {
					$err = 'نام کاربری یا رمز عبور اشتباه است.';
				} else {
					wp_safe_redirect( $this->self_url() );
					exit;
				}
			}
		}

		ob_start();
		$this->css();
		?>
		<div class="da-p-auth">
			<div class="da-p-auth-box">
				<div class="da-p-logo"><span class="da-logo-dot"></span> پنل مدیریت دستیار</div>
				<?php if ( $err ) : ?>
					<p class="da-p-err"><?php echo esc_html( $err ); ?></p>
				<?php endif; ?>
				<form method="post">
					<?php wp_nonce_field( self::LOGIN_NONCE, 'da_login_nonce' ); ?>
					<label>نام کاربری یا ایمیل</label>
					<input type="text" name="log" required autofocus>
					<label>رمز عبور</label>
					<input type="password" name="pwd" required>
					<button type="submit">ورود به پنل</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	protected function denied_screen() {
		ob_start();
		$this->css();
		?>
		<div class="da-p-auth">
			<div class="da-p-auth-box">
				<div class="da-p-logo"><span class="da-logo-dot"></span> پنل مدیریت دستیار</div>
				<p class="da-p-err">حساب کاربری شما به این پنل دسترسی ندارد. اگر فکر می‌کنید اشتباهی رخ داده، با مدیر مجموعه تماس بگیرید.</p>
				<p><a class="da-btn gray" href="<?php echo esc_url( wp_logout_url( $this->self_url() ) ); ?>">خروج از این حساب</a></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * پنل اصلی — سایدبار + محتوای ماژول انتخاب‌شده
	 * ---------------------------------------------------------------- */

	protected function panel_screen() {
		$owner = DA_Staff::current_user_is_owner();
		$sec   = sanitize_key( (string) ( $_GET['dsec'] ?? 'dash' ) );

		$pages  = DA_Modules::pages();
		$groups = DA_Modules::groups();

		// گروه‌بندی صفحه‌ها برای سایدبار (به همان ترتیب گروه‌ها)
		$by_group = array();
		foreach ( $pages as $key => $p ) {
			// گروه واقعی هر صفحه از روی اولین ماژول همان صفحه استخراج می‌شود
			$g = 'tools';
			foreach ( DA_Modules::all() as $m ) {
				if ( $m['page'] === $key ) {
					$g = $m['group'];
					break;
				}
			}
			$by_group[ $g ][ $key ] = $p;
		}

		ob_start();
		$this->css();
		?>
		<div class="da-p-shell">
			<aside class="da-p-side">
				<div class="da-p-logo"><span class="da-logo-dot"></span> پنل دستیار</div>
				<nav class="da-p-nav">
					<?php foreach ( $groups as $gkey => $glabel ) :
						if ( empty( $by_group[ $gkey ] ) ) { continue; }
						?>
						<p class="da-p-glabel"><?php echo esc_html( $glabel ); ?></p>
						<?php foreach ( $by_group[ $gkey ] as $pkey => $p ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'dsec', $pkey, $this->self_url() ) ); ?>" class="da-p-link<?php echo $sec === $pkey ? ' da-p-on' : ''; ?>"><?php echo esc_html( $p[0] ); ?></a>
						<?php endforeach; ?>
					<?php endforeach; ?>
					<?php if ( $owner ) : ?>
						<p class="da-p-glabel">مدیریت</p>
						<a href="<?php echo esc_url( add_query_arg( 'dsec', 'staff', $this->self_url() ) ); ?>" class="da-p-link<?php echo 'staff' === $sec ? ' da-p-on' : ''; ?>">👥 مدیریت کارمندان</a>
					<?php endif; ?>
				</nav>
				<a class="da-p-logout" href="<?php echo esc_url( wp_logout_url( $this->self_url() ) ); ?>">خروج از حساب</a>
			</aside>
			<main class="da-p-main">
				<?php
				if ( 'staff' === $sec ) {
					if ( $owner ) {
						DA_Page_Staff::render();
					} else {
						echo '<div class="da-card"><p class="da-empty">این بخش فقط برای مدیر اصلی مجموعه است.</p></div>';
					}
				} else {
					$this->render_page_section( $sec, $pages );
				}
				?>
			</main>
		</div>
		<?php
		return ob_get_clean();
	}

	/** دقیقاً همان منطق DA_Admin::render_page() — بدون کوچک‌ترین تغییر در ماژول‌ها */
	protected function render_page_section( $sec, $pages ) {
		$def = null;
		foreach ( $pages as $key => $p ) {
			if ( $key === $sec ) {
				$def = array( 'key' => $key, 'title' => $p[0], 'class' => $p[2] );
			}
		}
		if ( ! $def ) {
			$def = array( 'key' => 'dash', 'title' => $pages['dash'][0], 'class' => $pages['dash'][2] );
		}
		echo '<h1 class="da-page-title"><span class="da-logo-dot"></span>' . esc_html( $def['title'] ) . '</h1>';
		DA_Render::notices();

		$enabled = DA_Modules::enabled_on_page( $def['key'] );
		if ( ! $enabled ) {
			echo '<div class="da-card"><p class="da-empty">همه ماژول‌های این صفحه غیرفعال‌اند.</p></div>';
		}
		foreach ( $enabled as $id => $mod ) {
			$cls = $def['class'];
			if ( ! class_exists( $cls ) || ! method_exists( $cls, $mod['cb'] ) ) {
				continue;
			}
			ob_start();
			try {
				call_user_func( array( $cls, $mod['cb'] ) );
			} catch ( \Throwable $e ) {
				echo '<p class="da-empty">این کارت موقتاً در دسترس نیست.</p>';
			}
			$body = ob_get_clean();
			DA_Render::card( $mod, $body );
		}
	}

	protected function self_url() {
		global $post;
		return $post ? get_permalink( $post ) : home_url( '/' );
	}

	/* ------------------------------------------------------------------
	 * استایل — همان زبان بصری کنسول (سبز/سرمه‌ای) + چیدمان سایدبار مستقل از پیشخوان
	 * ---------------------------------------------------------------- */

	protected function css() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">';
		echo '<style>
		.da-p-shell,.da-p-auth{font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl}
		.da-p-shell *,.da-p-auth *{box-sizing:border-box}
		.da-logo-dot{width:10px;height:10px;border-radius:50%;background:#17a16d;display:inline-block;box-shadow:0 0 0 0 rgba(23,161,109,.5);animation:daPulse 1.8s ease-out infinite}
		@keyframes daPulse{0%{box-shadow:0 0 0 0 rgba(23,161,109,.5)}70%{box-shadow:0 0 0 9px rgba(23,161,109,0)}100%{box-shadow:0 0 0 0 rgba(23,161,109,0)}}

		.da-p-auth{min-height:70vh;display:flex;align-items:center;justify-content:center;padding:20px}
		.da-p-auth-box{background:#fff;border:1.5px solid #e3eee8;border-radius:16px;padding:30px 26px;max-width:360px;width:100%;box-shadow:0 10px 30px rgba(23,161,109,.08)}
		.da-p-logo{display:flex;align-items:center;gap:8px;font-weight:800;color:#242536;font-size:16px;margin-bottom:18px}
		.da-p-auth-box label{display:block;font-size:12.5px;font-weight:700;color:#242536;margin:12px 0 6px}
		.da-p-auth-box input{width:100%;border:1.5px solid #d6e6dc;border-radius:10px;padding:9px 12px;font-family:inherit;font-size:13px;background:#fbfdfc}
		.da-p-auth-box button{width:100%;margin-top:18px;background:#17a16d;border:1px solid #12875c;color:#fff;border-radius:10px;padding:11px;font-size:14px;font-weight:700;cursor:pointer}
		.da-p-auth-box button:hover{background:#12875c}
		.da-p-err{background:#fdecec;border:1px solid #f3caca;color:#a52828;border-radius:10px;padding:9px 12px;font-size:12.5px;margin-bottom:6px}

		.da-p-shell{display:flex;gap:22px;align-items:flex-start;max-width:1280px;margin:0 auto;padding:20px 14px}
		.da-p-side{position:sticky;top:20px;flex:0 0 220px;background:#fff;border:1.5px solid #e3eee8;border-radius:16px;padding:16px;max-height:calc(100vh - 40px);overflow-y:auto}
		.da-p-nav{display:flex;flex-direction:column}
		.da-p-glabel{font-size:10.5px;font-weight:800;color:#8aa093;margin:14px 0 4px;text-transform:uppercase}
		.da-p-glabel:first-child{margin-top:0}
		.da-p-link{display:block;padding:8px 10px;border-radius:9px;font-size:12.5px;font-weight:700;color:#3c4a44;text-decoration:none;margin-bottom:2px}
		.da-p-link:hover{background:#f4fbf7;color:#12875c}
		.da-p-on{background:#eafaf2;color:#12875c!important}
		.da-p-logout{display:block;margin-top:16px;padding-top:14px;border-top:1px solid #eef2f0;font-size:12px;color:#a52828;text-decoration:none;text-align:center}
		.da-p-main{flex:1;min-width:0}

		.da-page-title{display:flex;align-items:center;gap:10px;color:#242536;font-size:20px;margin:0 0 16px}
		.da-card{background:#fff;border:1.5px solid #e3eee8;border-radius:16px;margin:0 0 16px;box-shadow:0 4px 16px rgba(23,161,109,.07);overflow:hidden}
		.da-card-h{padding:12px 18px;background:linear-gradient(135deg,#f4fbf7,#eef7f2);border-bottom:1px solid #e8f2ec}
		.da-card-h h3{margin:0;color:#242536;font-size:15px;display:flex;align-items:center;gap:8px}
		.da-card-h p{margin:4px 0 0;color:#6f857b;font-size:12px}
		.da-modid{font-size:10px;background:#17a16d;color:#fff;border-radius:8px;padding:2px 7px;font-weight:700}
		.da-card-b{padding:16px 18px;overflow-x:auto}
		.da-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
		.da-kpi{background:#f8fcfa;border:1px solid #e3eee8;border-radius:12px;padding:14px;text-align:center}
		.da-kpi-v{display:block;font-size:22px;font-weight:900;color:#12875c}
		.da-kpi-l{display:block;font-size:11.5px;color:#6f857b;margin-top:4px}
		.da-kpi-s{display:block;font-size:10.5px;color:#8aa093;margin-top:2px}
		.da-table{width:100%;border-collapse:collapse;font-size:12.5px;background:#fff}
		.da-table th,.da-table td{padding:9px 10px;border-bottom:1px solid #eef2f0;text-align:right}
		.da-table th{background:#f8fcfa;color:#3c4a44;font-weight:800}
		.da-empty{color:#8aa093;font-size:12.5px}
		.da-pill{display:inline-block;border-radius:8px;padding:2px 9px;font-size:11px;font-weight:700}
		.da-pill-green{background:#e5f5ee;color:#0f5132}
		.da-pill-red{background:#fdecec;color:#922222}
		.da-pill-gray{background:#f1f3f2;color:#5b5e6e}
		.da-btn{display:inline-block;background:#17a16d;border:1px solid #12875c;color:#fff!important;border-radius:9px;padding:7px 16px;font-size:12.5px;font-weight:700;cursor:pointer;text-decoration:none!important}
		.da-btn:hover{background:#12875c}
		.da-btn.gray{background:#f1f3f2;border-color:#e3eee8;color:#3c4a44!important}
		.da-btn.gray:hover{background:#e6ece9}
		.da-btn.red{background:#fdecec;border-color:#f3caca;color:#a52828!important}
		.notice{border-radius:8px;padding:9px 14px;margin:0 0 14px;font-size:12.5px;border:1px solid}
		.notice-success{background:#ecf9f2;border-color:#c6e9d8;color:#0f5132}
		.notice-error{background:#fdf0f0;border-color:#f0c8c8;color:#a52828}
		.notice-warning{background:#fef8e8;border-color:#f0e0a8;color:#7a5b00}
		@media (max-width:820px){.da-p-shell{flex-direction:column}.da-p-side{position:static;width:100%;max-height:none}}
		</style>';
	}
}
