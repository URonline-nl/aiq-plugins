<?php
/**
 * Plugin Name: AIQ Huisstijl Afbeeldingen
 * Plugin URI: https://aiq.uronline.nl
 * Description: Bakt automatisch een duotone-tint en het AIQ-logo in elke nieuw geüploade afbeelding, met instellingen in plaats van hardcoded waarden. Voortzetting van de "Huisstijl images"-snippet v2.2 uit AIQ Kernfunctionaliteit. Ondersteunt optioneel een aparte behandeling voor WooCommerce-productafbeeldingen.
 * Version: 1.1.0
 * Author: URonline (https://uronline.nl)
 * License: GPL-2.0-or-later
 * Text Domain: aiq-huisstijl
 *
 * Werkt op wp_generate_attachment_metadata: bij ELKE nieuwe afbeeldingsupload
 * wordt verwerkt: 1) optioneel verkleinen naar max. maten, 2) iJsblauw-duotone
 * (histogram-auto-normalisatie -> grijstinten -> schaduw-lift -> multiply-kleur),
 * 3) logo-merkteken in Ice-cirkel links-onder. Bestandsnamen met een fragment
 * uit de skip-lijst krijgen geen duotone (standaard grafiek/chart/kaart/diagram/social).
 *
 * Belangrijk bij migratie: deactiveer de oude "Huisstijl images"-snippet in
 * AIQ Kern -> PHP-snippets vóór activering. De plugin respecteert en zet zowel
 * de eigen vlag (_aiq_hs_done) als de oude snippet-vlag (_aiq_house_style_done),
 * zodat dubbele verwerking toch voorkomen wordt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Standaardinstellingen (identiek aan de snippet v2.2 op aiq.uronline.nl)
 * ------------------------------------------------------------------------- */

function aiqhs_defaults() {
	return array(
		// Duotone
		'duotone_aan'      => 1,
		'kleur'            => '#C9EAF1',
		'lift'             => 0.55,
		'fill'             => 0.0,
		'normaliseren'     => 1,
		'clip'             => 0.005,
		// Logo
		'logo_aan'         => 1,
		'logo_id'          => 1589,   // attachment "AIQ icon-logo" (uc.png)
		'logo_pad'         => '2026/11/uc.png', // fallback als attachment ontbreekt
		'logo_alpha'       => 0.40,
		'cirkel'           => 1.45,
		'marge_x'          => 6,      // % vanaf links
		'marge_y'          => 3,      // % vanaf onder
		'logo_breedte'     => 9,      // % van beeldbreedte
		'logo_min'         => 26,     // px
		'logo_max'         => 46,     // px
		// Maten
		'resize_aan'       => 0,
		'max_breedte'      => 1920,
		'max_hoogte'       => 1080,
		// Overig
		'skip_lijst'       => 'grafiek, chart, kaart, diagram, social',
		'jpeg_kwaliteit'   => 90,
		'png_compressie'   => 6,
		// WooCommerce
		'woo_modus'        => 'uit', // 'uit' | 'duotone' (geen tint, wél logo) | 'alles' (geheel overslaan)
	);
}

function aiqhs_settings() {
	$s = get_option( 'aiqhs_settings', array() );
	if ( ! is_array( $s ) ) {
		$s = array();
	}
	return wp_parse_args( $s, aiqhs_defaults() );
}

/* -------------------------------------------------------------------------
 * Upload-hook (zelfde werkwijze als snippet v2.2, geparametriseerd)
 * ------------------------------------------------------------------------- */

add_filter( 'wp_generate_attachment_metadata', 'aiqhs_on_upload', 10, 2 );

function aiqhs_on_upload( $metadata, $attachment_id ) {

	$s = aiqhs_settings();

	// Alleen afbeeldingen
	$mime = get_post_mime_type( $attachment_id );
	if ( strpos( $mime, 'image/' ) !== 0 ) {
		return $metadata;
	}

	// Voorkom dubbele verwerking: eigen vlag én de vlag van de oude snippet
	if ( get_post_meta( $attachment_id, '_aiq_hs_done', true )
		|| get_post_meta( $attachment_id, '_aiq_house_style_done', true ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );

	// Logo oplossen: eerst attachment-ID uit instellingen, dan fallback-pad
	$logo_path = null;
	$logo_id   = (int) $s['logo_id'];
	if ( $logo_id && get_post( $logo_id ) && 'attachment' === get_post_type( $logo_id ) ) {
		$logo_path = get_attached_file( $logo_id );
	}
	if ( ! $logo_path || ! file_exists( $logo_path ) ) {
		$upload_dir = wp_upload_dir();
		$logo_path  = trailingslashit( $upload_dir['basedir'] ) . ltrim( (string) $s['logo_pad'], '/' );
	}
	$logo_aan = (int) $s['logo_aan'] && $logo_path && file_exists( $logo_path );

	// Hoofdbestand + alle gegenereerde maten
	$sizes = array( $file );
	if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		$upload_dir = wp_upload_dir();
		foreach ( $metadata['sizes'] as $size_info ) {
			$size_file = trailingslashit( $upload_dir['basedir'] ) . dirname( $metadata['file'] ) . '/' . $size_info['file'];
			if ( file_exists( $size_file ) ) {
				$sizes[] = $size_file;
			}
		}
	}

	// Skip-lijst: deze naamfragmenten krijgen geen duotone (wel logo, tenzij logo ook uit)
	$skip = array_filter( array_map( 'trim', explode( ',', (string) $s['skip_lijst'] ) ) );
	$is_skip = false;
	foreach ( $skip as $frag ) {
		if ( '' !== $frag && false !== stripos( basename( $file ), $frag ) ) {
			$is_skip = true;
			break;
		}
	}
	$do_duotone = (int) $s['duotone_aan'] && ! $is_skip;

	// WooCommerce: productafbeeldingen apart behandelen indien ingesteld.
	// Productafbeeldingen zijn attachments waarvan de parent een product (of variant)
	// is; ook de productgalerij en uitgelichte productafbeeldingen vallen daaronder.
	$woo_modus = (string) $s['woo_modus'];
	if ( 'uit' !== $woo_modus ) {
		$att        = get_post( $attachment_id );
		$parent     = ( $att && $att->post_parent ) ? get_post( $att->post_parent ) : null;
		$is_product = $parent && in_array( $parent->post_type, array( 'product', 'product_variation' ), true );
		if ( $is_product && 'alles' === $woo_modus ) {
			// Helemaal overslaan: bestand blijft onaangetast.
			return $metadata;
		}
		if ( $is_product && 'duotone' === $woo_modus ) {
			// Alleen duotone overslaan: logo (indien aan) blijft.
			$do_duotone = false;
		}
	}

	foreach ( $sizes as $target ) {
		aiqhs_process( $target, $do_duotone, $logo_aan ? $logo_path : null, $s );
	}

	// Na optioneel verkleinen: maten in de metadata bijwerken
	if ( (int) $s['resize_aan'] ) {
		$info = @getimagesize( $file );
		if ( $info && ! empty( $info[0] ) && ! empty( $info[1] ) ) {
			$metadata['width']  = $info[0];
			$metadata['height'] = $info[1];
		}
	}

	update_post_meta( $attachment_id, '_aiq_hs_done', 1 );
	// Ook de oude snippet-vlag zetten, zodat de (eventueel nog actieve) snippet niet dubbel verwerkt
	update_post_meta( $attachment_id, '_aiq_house_style_done', 1 );

	return $metadata;
}

/* -------------------------------------------------------------------------
 * Eén bestand verwerken: laden -> optioneel verkleinen -> duotone -> logo -> opslaan
 * ------------------------------------------------------------------------- */

function aiqhs_process( $file, $do_duotone, $logo_path, $s ) {

	if ( preg_match( '/\.jpe?g$/i', $file ) ) {
		$image = imagecreatefromjpeg( $file );
	} elseif ( preg_match( '/\.png$/i', $file ) ) {
		$image = imagecreatefrompng( $file );
	} elseif ( preg_match( '/\.gif$/i', $file ) ) {
		$image = imagecreatefromgif( $file );
	} elseif ( preg_match( '/\.webp$/i', $file ) ) {
		$image = function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $file ) : null;
	} else {
		$image = null;
	}
	if ( ! $image ) {
		return false;
	}

	if ( function_exists( 'imagepalettetotruecolor' ) && ! imageistruecolor( $image ) ) {
		imagepalettetotruecolor( $image );
	}

	imagealphablending( $image, true );
	imagesavealpha( $image, true );

	$w = imagesx( $image );
	$h = imagesy( $image );

	// — 0. Optioneel verkleinen naar max. maten (verhouding blijft behouden) —
	if ( (int) $s['resize_aan'] ) {
		$max_w = max( 1, (int) $s['max_breedte'] );
		$max_h = max( 1, (int) $s['max_hoogte'] );
		if ( $w > $max_w || $h > $max_h ) {
			$factor = min( $max_w / $w, $max_h / $h );
			$nw = max( 1, (int) round( $w * $factor ) );
			$nh = max( 1, (int) round( $h * $factor ) );
			$resized = imagecreatetruecolor( $nw, $nh );
			imagealphablending( $resized, false );
			imagesavealpha( $resized, true );
			imagefill( $resized, 0, 0, imagecolorallocatealpha( $resized, 0, 0, 0, 127 ) );
			imagecopyresampled( $resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h );
			imagedestroy( $image );
			$image = $resized;
			$w = $nw;
			$h = $nh;
		}
	}

	// — 1. Duotone met auto-normalisatie (B-variant uit snippet v2.0-v2.2) —
	if ( $do_duotone ) {

		$rgb = array();
		if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim( (string) $s['kleur'] ), $rgb ) ) {
			$hex = $rgb[1];
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$kr = hexdec( substr( $hex, 0, 2 ) );
			$kg = hexdec( substr( $hex, 2, 2 ) );
			$kb = hexdec( substr( $hex, 4, 2 ) );
		} else {
			$kr = 201; $kg = 234; $kb = 241; // #C9EAF1
		}
		$lift = (float) $s['lift'];
		$fill = (float) $s['fill'];
		$bp   = - $lift / ( 1 - $lift );

		// lichtheidshistogram meten
		$hist = array_fill( 0, 256, 0 );
		for ( $yy = 0; $yy < $h; $yy++ ) {
			for ( $xx = 0; $xx < $w; $xx++ ) {
				$idx = imagecolorat( $image, $xx, $yy );
				$r = ( $idx >> 16 ) & 0xFF;
				$g = ( $idx >> 8 ) & 0xFF;
				$b = $idx & 0xFF;
				$lum = (int) round( 0.299 * $r + 0.587 * $g + 0.114 * $b );
				if ( $lum > 255 ) $lum = 255;
				$hist[ $lum ]++;
			}
		}

		// clip-grenzen
		$totaal  = $w * $h;
		$drempel = (int) round( $totaal * (float) $s['clip'] );
		if ( (int) $s['normaliseren'] && $drempel < $totaal ) {
			$lo = 0; $hi = 255;
			$c = 0;
			for ( $i = 0; $i <= 255; $i++ ) {
				$c += $hist[ $i ];
				if ( $c > $drempel ) { $lo = $i; break; }
			}
			$c = 0;
			for ( $i = 255; $i >= 0; $i-- ) {
				$c += $hist[ $i ];
				if ( $c > $drempel ) { $hi = $i; break; }
			}
			if ( $hi - $lo < 32 ) { $lo = 0; $hi = 255; }
		} else {
			$lo = 0; $hi = 255;
		}
		$bereik = max( 1, $hi - $lo );

		// LUT: normalisatie -> lift-curve -> multiply-kleur
		$lut = array();
		for ( $i = 0; $i <= 255; $i++ ) {
			$l = ( $i - $lo ) / $bereik;
			if ( $l < 0 ) $l = 0;
			if ( $l > 1 ) $l = 1;
			$g = ( $l - $bp ) / ( 1 - $bp );
			if ( $g < 0 ) $g = 0;
			if ( $g > 1 ) $g = 1;
			$r  = (int) round( $g * $kr );
			$gg = (int) round( $g * $kg );
			$b  = (int) round( $g * $kb );
			$lut[ $i ] = ( $r << 16 ) | ( $gg << 8 ) | $b;
		}

		// toepassen
		for ( $yy = 0; $yy < $h; $yy++ ) {
			for ( $xx = 0; $xx < $w; $xx++ ) {
				$idx = imagecolorat( $image, $xx, $yy );
				$r = ( $idx >> 16 ) & 0xFF;
				$g = ( $idx >> 8 ) & 0xFF;
				$b = $idx & 0xFF;
				$lum = (int) round( 0.299 * $r + 0.587 * $g + 0.114 * $b );
				if ( $lum > 255 ) $lum = 255;
				$nieuw = $lut[ $lum ];
				if ( $fill > 0.0 ) {
					$nr = ( ( $nieuw >> 16 ) & 0xFF ) * ( 1 - $fill ) + $r * $fill;
					$ng = ( ( $nieuw >> 8 ) & 0xFF ) * ( 1 - $fill ) + $g * $fill;
					$nb = ( $nieuw & 0xFF ) * ( 1 - $fill ) + $b * $fill;
					$nieuw = ( (int) round( $nr ) << 16 ) | ( (int) round( $ng ) << 8 ) | (int) round( $nb );
				}
				imagesetpixel( $image, $xx, $yy, $nieuw );
			}
		}
	}

	// — 2. Logo in Ice-cirkel met uitwaaiering (v2.2-stijl) —
	if ( $logo_path ) {

		$logo = imagecreatefrompng( $logo_path );
		if ( $logo ) {

			imagealphablending( $logo, false );
			imagesavealpha( $logo, true );

			$lw = imagesx( $logo );
			$lh = imagesy( $logo );

			// Doelgrootte logo: % van beeldbreedte, met min/max in px
			$dst_w = max( (int) $s['logo_min'], min( (int) $s['logo_max'], (int) round( $w * ( (float) $s['logo_breedte'] / 100 ) ) ) );
			$dst_h = $dst_w;
			$dst_x = (int) round( $w * ( (float) $s['marge_x'] / 100 ) );
			$dst_y = $h - $dst_h - (int) round( $h * ( (float) $s['marge_y'] / 100 ) );

			// Stap 1: logo schalen (alpha blijft behouden)
			$logo_resized = imagecreatetruecolor( $dst_w, $dst_h );
			imagealphablending( $logo_resized, false );
			imagesavealpha( $logo_resized, true );
			imagefill( $logo_resized, 0, 0, imagecolorallocatealpha( $logo_resized, 0, 0, 0, 127 ) );
			imagecopyresampled( $logo_resized, $logo, 0, 0, 0, 0, $dst_w, $dst_h, $lw, $lh );

			// Stap 2: logo-dekking in het alpha-kanaal
			$alpha_pct = (float) $s['logo_alpha'];
			for ( $yy = 0; $yy < $dst_h; $yy++ ) {
				for ( $xx = 0; $xx < $dst_w; $xx++ ) {
					$rgba = imagecolorsforindex( $logo_resized, imagecolorat( $logo_resized, $xx, $yy ) );
					$old_alpha = $rgba['alpha'];
					if ( $old_alpha < 127 ) {
						$new_alpha = 127 - (int) round( ( 127 - $old_alpha ) * $alpha_pct );
						$col = imagecolorallocatealpha( $logo_resized, $rgba['red'], $rgba['green'], $rgba['blue'], $new_alpha );
						imagesetpixel( $logo_resized, $xx, $yy, $col );
					}
				}
			}

			// Stap 3: Ice 100-cirkel met kleine uitwaaiering
			$circel_d = (int) round( $dst_w * max( 1.2, (float) $s['cirkel'] ) );
			$layer_grootte = $circel_d + 8;
			$layer = imagecreatetruecolor( $layer_grootte, $layer_grootte );
			imagealphablending( $layer, false );
			imagesavealpha( $layer, true );
			imagefill( $layer, 0, 0, imagecolorallocatealpha( $layer, 0, 0, 0, 127 ) );

			$cx = (int) round( $layer_grootte / 2 );
			$cy = $cx;
			$max_r = (int) round( $circel_d / 2 );
			$feather_r = max( 2, (int) round( $max_r * 0.18 ) );

			for ( $r = $max_r; $r >= 1; $r-- ) {
				if ( $r <= $max_r - $feather_r ) {
					$alpha = 0;
				} else {
					$p = ( $r - ( $max_r - $feather_r ) ) / $feather_r;
					$alpha = (int) round( 127 * $p );
				}
				$col = imagecolorallocatealpha( $layer, 240, 248, 250, $alpha ); // Ice 100
				imagefilledellipse( $layer, $cx, $cy, $r * 2, $r * 2, $col );
			}

			// Stap 3b: logo gecentreerd middenin de cirkel
			imagealphablending( $layer, true );
			imagecopyresampled( $layer, $logo_resized, $cx - (int) round( $dst_w / 2 ), $cy - (int) round( $dst_h / 2 ), 0, 0, $dst_w, $dst_h, $dst_w, $dst_h );

			// Stap 4: cirkel met logo op de foto zetten
			$px = $dst_x + (int) round( $dst_w / 2 ) - $cx;
			$py = $dst_y + (int) round( $dst_h / 2 ) - $cy;
			imagealphablending( $image, true );
			imagecopyresampled( $image, $layer, $px, $py, 0, 0, $layer_grootte, $layer_grootte, $layer_grootte, $layer_grootte );

			imagedestroy( $logo );
			imagedestroy( $logo_resized );
			imagedestroy( $layer );
		}
	}

	// — opslaan —
	$q  = max( 10, min( 100, (int) $s['jpeg_kwaliteit'] ) );
	$pq = max( 0, min( 9, (int) $s['png_compressie'] ) );
	if ( preg_match( '/\.jpe?g$/i', $file ) ) {
		imagejpeg( $image, $file, $q );
	} elseif ( preg_match( '/\.png$/i', $file ) ) {
		imagepng( $image, $file, $pq );
	} elseif ( preg_match( '/\.gif$/i', $file ) ) {
		imagegif( $image, $file );
	} elseif ( preg_match( '/\.webp$/i', $file ) && function_exists( 'imagewebp' ) ) {
		imagewebp( $image, $file, $q );
	}

	imagedestroy( $image );
	return true;
}

/* -------------------------------------------------------------------------
 * Instellingenpagina (Instellingen -> AIQ Huisstijl)
 * ------------------------------------------------------------------------- */

add_action( 'admin_menu', 'aiqhs_admin_menu' );
function aiqhs_admin_menu() {
	add_options_page(
		'AIQ Huisstijl Afbeeldingen',
		'AIQ Huisstijl',
		'manage_options',
		'aiq-huisstijl',
		'aiqhs_settings_page'
	);
}

add_action( 'admin_init', 'aiqhs_register_settings' );
function aiqhs_register_settings() {
	register_setting( 'aiqhs', 'aiqhs_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'aiqhs_sanitize',
	) );
}

function aiqhs_sanitize( $in ) {
	$d = aiqhs_defaults();
	$in = is_array( $in ) ? $in : array();

	$out = array();
	$out['duotone_aan']    = empty( $in['duotone_aan'] ) ? 0 : 1;
	$out['kleur']          = isset( $in['kleur'] ) ? sanitize_text_field( $in['kleur'] ) : $d['kleur'];
	if ( ! preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $out['kleur'] ) ) {
		$out['kleur'] = $d['kleur'];
	}
	$out['lift']           = isset( $in['lift'] ) ? max( 0, min( 0.95, (float) $in['lift'] ) ) : $d['lift'];
	$out['fill']           = isset( $in['fill'] ) ? max( 0, min( 1, (float) $in['fill'] ) ) : $d['fill'];
	$out['normaliseren']   = empty( $in['normaliseren'] ) ? 0 : 1;
	$out['clip']           = isset( $in['clip'] ) ? max( 0, min( 0.2, (float) $in['clip'] ) ) : $d['clip'];
	$out['logo_aan']       = empty( $in['logo_aan'] ) ? 0 : 1;
	$out['logo_id']        = isset( $in['logo_id'] ) ? max( 0, (int) $in['logo_id'] ) : $d['logo_id'];
	$out['logo_pad']       = isset( $in['logo_pad'] ) ? sanitize_text_field( str_replace( '..', '', $in['logo_pad'] ) ) : $d['logo_pad'];
	$out['logo_alpha']     = isset( $in['logo_alpha'] ) ? max( 0, min( 1, (float) $in['logo_alpha'] ) ) : $d['logo_alpha'];
	$out['cirkel']         = isset( $in['cirkel'] ) ? max( 1.2, min( 3, (float) $in['cirkel'] ) ) : $d['cirkel'];
	$out['marge_x']        = isset( $in['marge_x'] ) ? max( 0, min( 40, (float) $in['marge_x'] ) ) : $d['marge_x'];
	$out['marge_y']        = isset( $in['marge_y'] ) ? max( 0, min( 40, (float) $in['marge_y'] ) ) : $d['marge_y'];
	$out['logo_breedte']   = isset( $in['logo_breedte'] ) ? max( 2, min( 30, (float) $in['logo_breedte'] ) ) : $d['logo_breedte'];
	$out['logo_min']       = isset( $in['logo_min'] ) ? max( 10, min( 200, (int) $in['logo_min'] ) ) : $d['logo_min'];
	$out['logo_max']       = isset( $in['logo_max'] ) ? max( 10, min( 300, (int) $in['logo_max'] ) ) : $d['logo_max'];
	$out['resize_aan']     = empty( $in['resize_aan'] ) ? 0 : 1;
	$out['max_breedte']    = isset( $in['max_breedte'] ) ? max( 100, min( 8000, (int) $in['max_breedte'] ) ) : $d['max_breedte'];
	$out['max_hoogte']     = isset( $in['max_hoogte'] ) ? max( 100, min( 8000, (int) $in['max_hoogte'] ) ) : $d['max_hoogte'];
	$out['skip_lijst']     = isset( $in['skip_lijst'] ) ? sanitize_text_field( $in['skip_lijst'] ) : $d['skip_lijst'];
	$out['jpeg_kwaliteit'] = isset( $in['jpeg_kwaliteit'] ) ? max( 10, min( 100, (int) $in['jpeg_kwaliteit'] ) ) : $d['jpeg_kwaliteit'];
	$out['png_compressie'] = isset( $in['png_compressie'] ) ? max( 0, min( 9, (int) $in['png_compressie'] ) ) : $d['png_compressie'];
	$out['woo_modus']      = isset( $in['woo_modus'] ) && in_array( $in['woo_modus'], array( 'uit', 'duotone', 'alles' ), true ) ? $in['woo_modus'] : $d['woo_modus'];

	return $out;
}

function aiqhs_settings_page() {
	$s = aiqhs_settings();
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen rechten.' );
	}
	?>
	<div class="wrap">
		<h1>AIQ Huisstijl Afbeeldingen</h1>
		<p>Elke nieuw geüploade afbeelding krijgt automatisch de duotone-tint en het AIQ-logo. Bestandsnamen met een fragment uit de skip-lijst slaan de duotone over (handig voor datagrafieken en social cards). Bestaande afbeeldingen worden niet achteraf gewijzigd.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'aiqhs' ); ?>
			<table class="form-table">

				<tr><th colspan="2"><h2>Duotone</h2></th></tr>
				<tr>
					<th>Duotone toepassen</th>
					<td><label><input type="checkbox" name="aiqhs_settings[duotone_aan]" value="1" <?php checked( (int) $s['duotone_aan'], 1 ); ?>> iJsblauwe tint over elke afbeelding</label></td>
				</tr>
				<tr>
					<th>Multiply-kleur</th>
					<td><input type="text" name="aiqhs_settings[kleur]" value="<?php echo esc_attr( $s['kleur'] ); ?>" class="small-text"> (hex, bijv. #C9EAF1)</td>
				</tr>
				<tr>
					<th>Schaduw-lift</th>
					<td><input type="number" step="0.05" min="0" max="0.95" name="aiqhs_settings[lift]" value="<?php echo esc_attr( $s['lift'] ); ?>" class="small-text"> (0-0,95; hoger = lichtere schaduwen)</td>
				</tr>
				<tr>
					<th>Oorspronkelijke kleur behouden</th>
					<td><input type="number" step="0.05" min="0" max="1" name="aiqhs_settings[fill]" value="<?php echo esc_attr( $s['fill'] ); ?>" class="small-text"> (0 = puur duotone, 1 = geen tint)</td>
				</tr>
				<tr>
					<th>Auto-normalisatie</th>
					<td><label><input type="checkbox" name="aiqhs_settings[normaliseren]" value="1" <?php checked( (int) $s['normaliseren'], 1 ); ?>> vlakke bronnen krijgen eerst contrast</label></td>
				</tr>
				<tr>
					<th>Clip</th>
					<td><input type="number" step="0.005" min="0" max="0.2" name="aiqhs_settings[clip]" value="<?php echo esc_attr( $s['clip'] ); ?>" class="small-text"> (aandeel pixels dat bij normalisatie weggeklapt wordt)</td>
				</tr>

				<tr><th colspan="2"><h2>Logo</h2></th></tr>
				<tr>
					<th>Logo toepassen</th>
					<td><label><input type="checkbox" name="aiqhs_settings[logo_aan]" value="1" <?php checked( (int) $s['logo_aan'], 1 ); ?>> AIQ-merklogo in Ice-cirkel links-onder</label></td>
				</tr>
				<tr>
					<th>Logo (attachment-ID)</th>
					<td><input type="number" min="0" name="aiqhs_settings[logo_id]" value="<?php echo esc_attr( (int) $s['logo_id'] ); ?>" class="small-text"> ID van het logo in de mediabibliotheek (PNG met transparantie). Kan niet laden? Gebruik dan het fallback-pad hieronder.</td>
				</tr>
				<tr>
					<th>Fallback-pad logo</th>
					<td><input type="text" size="40" name="aiqhs_settings[logo_pad]" value="<?php echo esc_attr( $s['logo_pad'] ); ?>"> pad binnen wp-content/uploads/ (bijv. 2026/11/uc.png)</td>
				</tr>
				<tr>
					<th>Logo-dekking</th>
					<td><input type="number" step="0.05" min="0" max="1" name="aiqhs_settings[logo_alpha]" value="<?php echo esc_attr( $s['logo_alpha'] ); ?>" class="small-text"> (0-1)</td>
				</tr>
				<tr>
					<th>Cirkelgrootte</th>
					<td><input type="number" step="0.05" min="1.2" max="3" name="aiqhs_settings[cirkel]" value="<?php echo esc_attr( $s['cirkel'] ); ?>" class="small-text"> (× logo-breedte)</td>
				</tr>
				<tr>
					<th>Positie</th>
					<td>marge links <input type="number" step="0.5" min="0" max="40" name="aiqhs_settings[marge_x]" value="<?php echo esc_attr( $s['marge_x'] ); ?>" class="tiny-text"> %, marge onder <input type="number" step="0.5" min="0" max="40" name="aiqhs_settings[marge_y]" value="<?php echo esc_attr( $s['marge_y'] ); ?>" class="tiny-text"> %</td>
				</tr>
				<tr>
					<th>Logo-breedte</th>
					<td><input type="number" step="0.5" min="2" max="30" name="aiqhs_settings[logo_breedte]" value="<?php echo esc_attr( $s['logo_breedte'] ); ?>" class="tiny-text"> % van beeldbreedte, min <input type="number" min="10" max="200" name="aiqhs_settings[logo_min]" value="<?php echo esc_attr( (int) $s['logo_min'] ); ?>" class="tiny-text"> px, max <input type="number" min="10" max="300" name="aiqhs_settings[logo_max]" value="<?php echo esc_attr( (int) $s['logo_max'] ); ?>" class="tiny-text"> px</td>
				</tr>

				<tr><th colspan="2"><h2>Maten</h2></th></tr>
				<tr>
					<th>Automatisch verkleinen</th>
					<td><label><input type="checkbox" name="aiqhs_settings[resize_aan]" value="1" <?php checked( (int) $s['resize_aan'], 1 ); ?>> grotere uploads verkleinen naar max. maten</label></td>
				</tr>
				<tr>
					<th>Max. afmetingen</th>
					<td>breedte <input type="number" min="100" max="8000" name="aiqhs_settings[max_breedte]" value="<?php echo esc_attr( (int) $s['max_breedte'] ); ?>" class="small-text"> px, hoogte <input type="number" min="100" max="8000" name="aiqhs_settings[max_hoogte]" value="<?php echo esc_attr( (int) $s['max_hoogte'] ); ?>" class="small-text"> px (verhouding blijft behouden)</td>
				</tr>

				<tr><th colspan="2"><h2>Overig</h2></th></tr>
				<tr>
					<th>Skip-lijst</th>
					<td><input type="text" size="60" name="aiqhs_settings[skip_lijst]" value="<?php echo esc_attr( $s['skip_lijst'] ); ?>"> komma-gescheiden naamfragmenten; bestandsnamen hiermee krijgen géén duotone (logo blijft, tenzij uit)</td>
				</tr>
				<tr>
					<th>Compressie</th>
					<td>JPEG-kwaliteit <input type="number" min="10" max="100" name="aiqhs_settings[jpeg_kwaliteit]" value="<?php echo esc_attr( (int) $s['jpeg_kwaliteit'] ); ?>" class="tiny-text"> (10-100), PNG-compressie <input type="number" min="0" max="9" name="aiqhs_settings[png_compressie]" value="<?php echo esc_attr( (int) $s['png_compressie'] ); ?>" class="tiny-text"> (0-9)</td>
				</tr>
				<tr>
					<th>WooCommerce</th>
					<td>
						<select name="aiqhs_settings[woo_modus]">
							<option value="uit" <?php selected( (string) $s['woo_modus'], 'uit' ); ?>>Productafbeeldingen normaal stylen</option>
							<option value="duotone" <?php selected( (string) $s['woo_modus'], 'duotone' ); ?>>Productafbeeldingen: geen duotone, wél logo</option>
							<option value="alles" <?php selected( (string) $s['woo_modus'], 'alles' ); ?>>Productafbeeldingen: helemaal overslaan</option>
						</select>
						<p class="description">Geldt voor afbeeldingen die aan een product (of productvariant) gekoppeld zijn, inclusief de productgalerij en uitgelichte productafbeelding. Handig voor webshops: productfoto's houden zo hun echte kleuren. Alleen relevant als WooCommerce actief is.</p>
					</td>
				</tr>

			</table>
			<?php submit_button(); ?>
		</form>
		<h2>Handmatig nabewerken</h2>
		<p>Wil je een reeds geüploade afbeelding alsnog van huisstijl voorzien? Verwijder dan in de mediabibliotheek de meta-velden <code>_aiq_hs_done</code> en <code>_aiq_house_style_done</code> en genereer de thumbnails opnieuw (bijv. met de plugin Regenerate Thumbnails), of verwijder de afbeelding en upload hem opnieuw.</p>
	</div>
	<?php
}
