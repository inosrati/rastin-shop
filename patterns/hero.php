<?php
/**
 * Title: بخش قهرمان (Hero)
 * Slug: rastin/hero
 * Categories: rastin, banner, call-to-action
 * Description: بخش معرفی بالای صفحه اصلی با عنوان، توضیح و دکمه.
 * Keywords: hero, banner, cta, قهرمان
 * Block Types: core/cover
 * Viewport Width: 1400
 */
?>
<!-- wp:cover {"overlayColor":"contrast","dimRatio":80,"minHeight":460,"gradient":"primary-accent","contentPosition":"center center","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);min-height:460px"><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-80 has-background-dim has-background-gradient has-primary-accent-gradient-background"></span><div class="wp-block-cover__inner-container">
	<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"clamp(2.2rem, 5vw, 3.5rem)","lineHeight":"1.2"}},"textColor":"base"} -->
	<h1 class="wp-block-heading has-text-align-center has-base-color has-text-color" style="font-size:clamp(2.2rem, 5vw, 3.5rem);line-height:1.2"><?php esc_html_e( 'خریدی مطمئن، تجربه‌ای متفاوت', 'rastin' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1.15rem"},"color":{"text":"#eef2f9"}}} -->
	<p class="has-text-align-center has-text-color" style="color:#eef2f9;font-size:1.15rem"><?php esc_html_e( 'جدیدترین محصولات با بهترین قیمت، ارسال سریع به سراسر کشور و ضمانت بازگشت کالا.', 'rastin' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--40)">
		<!-- wp:button {"backgroundColor":"base","textColor":"contrast","style":{"typography":{"fontWeight":"600"}}} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-base-background-color has-text-color has-background wp-element-button" href="/shop" style="font-weight:600"><?php esc_html_e( 'مشاهده فروشگاه', 'rastin' ); ?></a></div>
		<!-- /wp:button -->
		<!-- wp:button {"className":"is-style-outline","textColor":"base","style":{"border":{"color":"#ffffff","width":"1px"}}} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-base-color has-text-color has-border-color wp-element-button" href="#featured" style="border-color:#ffffff;border-width:1px"><?php esc_html_e( 'محصولات ویژه', 'rastin' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->
