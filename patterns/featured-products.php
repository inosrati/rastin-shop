<?php
/**
 * Title: محصولات ویژه
 * Slug: rastin-shop/featured-products
 * Categories: rastin-shop, woocommerce, posts
 * Description: نمایش شبکه‌ای محصولات ویژه/جدید ووکامرس با عنوان بخش.
 * Keywords: products, woocommerce, featured, محصولات, فروشگاه
 * Viewport Width: 1400
 */
?>
<!-- wp:group {"tagName":"section","anchor":"featured","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<section id="featured" class="wp-block-group has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:heading {"textAlign":"center","level":2} -->
	<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'محصولات ویژه', 'rastin' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","textColor":"muted","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} -->
	<p class="has-text-align-center has-muted-color has-text-color" style="margin-bottom:var(--wp--preset--spacing--50)"><?php esc_html_e( 'منتخبی از پرفروش‌ترین و جدیدترین محصولات فروشگاه', 'rastin' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:woocommerce/all-products {"columns":4,"rows":2,"alignButtons":true,"contentVisibility":{"orderBy":true},"orderby":"date","layoutConfig":[["woocommerce/product-image",{"imageSizing":"thumbnail"}],["woocommerce/product-title"],["woocommerce/product-price"],["woocommerce/product-rating"],["woocommerce/product-button"]],"align":"wide"} -->
	<div class="wp-block-woocommerce-all-products wc-block-all-products alignwide" data-attributes="{&quot;alignButtons&quot;:true,&quot;columns&quot;:4,&quot;contentVisibility&quot;:{&quot;orderBy&quot;:true},&quot;layoutConfig&quot;:[[&quot;woocommerce/product-image&quot;,{&quot;imageSizing&quot;:&quot;thumbnail&quot;}],[&quot;woocommerce/product-title&quot;],[&quot;woocommerce/product-price&quot;],[&quot;woocommerce/product-rating&quot;],[&quot;woocommerce/product-button&quot;]],&quot;orderby&quot;:&quot;date&quot;,&quot;rows&quot;:2}"></div>
	<!-- /wp:woocommerce/all-products -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/shop"><?php esc_html_e( 'مشاهده همه محصولات', 'rastin' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
