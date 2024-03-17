<?php
/**
 * Plugin Name: Blarg
 * Description: A plugin that does nothing.
 * Version: 1.0
 * Author: CloudFest Hackathon
 */

/**
 * Converts parsed block format into the special
 * snowflake template format expected in JS.
 *
 * Example:
 *
 *     // input
 *     [
 *         [ 'blockName' => 'core/paragraph'
 *         , 'attrs' => [ 'content' => '<b>Strong<i>em' ]
 *         , 'innerBlocks' => [ $blocks ]
 *         , 'innerHTML' => '…'
 *         , 'innerContent' => [ ... ]
 *     ]
 *
 *     // output
 *     [ [ 'core/paragraph', [ 'content' => '<b>strong<i>em' ], [ $blocks ] ]
 *
 * @param $block
 *
 * @return array[].
 */
function php2silly( $blocks ) {
	$template = [];
	foreach($blocks as $block) {
		if ( null === $block['blockName'] && empty( trim( $block['innerHTML'] ) ) ) {
			continue;
		}

		$entry = [
			$block['blockName'],
			$block['attrs'],
		];
		if ( count( $block['innerBlocks'] ) > 0 ) {
			$entry[] = php2silly( $block['innerBlocks'] );
		}
		$template[] = $entry;
	}
	return $template;
}

add_action(
	'init',
	function() {
		register_post_type(
			'wp_data_type',
			array(
				'label'         => 'Data Type',
				'public'        => true,
				'show_in_menu'  => true,
				'show_in_rest'  => true,
			)
		);

		$data_types = new WP_Query( [ 'post_type' => 'wp_data_type' ] );

		while ( $data_types->have_posts() ) {
			$data_types->the_post();
			$data_type      = get_post();
			$data_type_info = get_post_meta( get_the_ID(), 'data_type_info', true );

//			if ( '' === $data_type_info || false === $data_type_info ) {
//				continue;
//			}
//
//			$data_type_info = json_decode( $data_type_info );
//			if ( JSON_ERROR_NONE !== json_last_error() ) {
//				continue;
//			}

//			echo "<plaintext>" . var_export( parse_blocks( $data_type->post_content ), true );
//			die();

			register_post_type(
				strtolower( $data_type->post_title ),
				array(
					'label'         => $data_type->post_title,
					'public'        => true,
					'show_in_menu'  => true,
					'show_in_rest'  => true,
					'icon'          => 'dashicons-admin-site',
					'template'      => php2silly( parse_blocks( $data_type->post_content ) ),
					'template_lock' => 'insert',
				)
			);
		}
	},
	0
);

function my_custom_gutenberg_scripts() {
	wp_enqueue_script(
		'my-custom-inspector-control',
		plugin_dir_url( __FILE__ ) . '/edit-script.js', // Adjust the path to where your JS file is located.
		array( 'wp-blocks', 'wp-dom-ready', 'wp-edit-post' )
	);
}
add_action( 'enqueue_block_editor_assets', 'my_custom_gutenberg_scripts' );

function jsonForCPT( $post_id ) {
	$post = get_post( $post_id );
	$template = null;

	$query = new WP_Query( [ 'post_type' => 'wp_data_type', ] );
	while ( $query->have_posts() ) {
		$query->the_post();
		$template = get_post();

		if ( $post->post_type === strtolower( $template->post_title ) ) {
			break;
		}

		$template = null;
	}

	if ( null === $template ) {
		return 'null';
	}

	$post_json = blocks_to_json( parse_blocks( $post->post_content ) );
	return $post_json;

	return hydrate_blocks_with_structured_data(parse_blocks($template->post_content), $post_json);
}

/**
 * Recursively parses block markup and extracts metadata attributes.
 *
 * @param  array  $blocks  Array of block objects.
 *
 * @return array Array of metadata attribute mappings.
 */
function blocks_to_json( $blocks ) {
	$json = [];

	foreach ( $blocks as $block ) {
		// Check if the block has metadata attribute
		if ( isset( $block['attrs']['metadata']['formFieldNames'] ) && is_array( $block['attrs']['metadata']['formFieldNames'] ) ) {
			$metadata = $block['attrs']['metadata']['formFieldNames'];
			foreach ( $metadata as $attributeName => $fieldName ) {
				if ( $attributeName !== "metadata" && isset( $block['attrs'][ $attributeName ] ) ) {
					$json[ $fieldName ] = isset($block['attrs'][ $attributeName ]) ? $block['attrs'][ $attributeName ] : null;
				}
			}
		}

		// Recursively process nested blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			$nestedMappings = blocks_to_json( $block['innerBlocks'] );
			$json = array_merge( $json, $nestedMappings );
		}
	}

	return $json;
}

function hydrate_blocks_with_structured_data( $blocks, $structuredData ) {
	foreach ( $blocks as $index => $block ) {
		// Check if the block has metadata attribute
		if ( isset( $block['attrs']['metadata']['formFieldNames'] ) && is_array( $block['attrs']['metadata']['formFieldNames'] ) ) {
			$metadata = $block['attrs']['metadata']['formFieldNames'];
			foreach ( $metadata as $attributeName => $fieldName ) {
				if ( $attributeName !== "metadata" && isset( $structuredData[ $fieldName ] ) ) {
					$blocks[ $index ]['attrs'][ $attributeName ] = $structuredData[ $fieldName ];
				}
			}
		}

		// Recursively process nested blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $index ]['innerBlocks'] = hydrate_blocks_with_structured_data( $block['innerBlocks'], $structuredData );
		}
	}

	return $blocks;
}
//
///**
// * Example usage
// */
//$post_content     = '<!-- wp:paragraph {"content": "The WordPress Foundation","metadata":{"formFieldNames":{"content":"company_name"}}} -->
//<p>The WordPress Foundation</p>
//<!-- /wp:paragraph -->';
//$blocks           = parse_blocks( $post_content );
//$metadataMappings = blocks_to_structured_data( $blocks );
//
//// Output the result
//echo '<pre>';
//var_dump( $metadataMappings );
//echo '</pre>';
//
//print_r(
//	hydrate_blocks_with_structured_data(
//		$blocks,
//		[
//			'company_name' => 'A new company name',
//		]
//	)
//);
//die();
//
//die();
