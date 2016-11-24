<?php
/**
 * Default manifest file
 *
 * @var array
 */
$settings = array(
	'xml' => array(
		'enabled'    => true,
		'use_upload' => true,
		'path'       => false,
	),
	'import' => array(
		'chunk_size'            => $this->chunk_size,
		'regenerate_chunk_size' => 3,
	),
	'remap' => array(
		'post_meta' => array(),
		'term_meta' => array(),
		'options'   => array(),
	),
	'export' => array(
		'message' => __( 'Export all content with TemplateMonster Data Export tool', 'cherry-data-importer' ),
		'logo'    => $this->url( 'assets/img/monster-logo.png' ),
		'options' => array(),
	),
	'success-links' => array(
		'home' => array(
			'label'  => __( 'View your site', 'cherry-data-importer' ),
			'type'   => 'primary',
			'target' => '_self',
			'url'    => home_url( '/' ),
		),
		'customize' => array(
			'label'  => __( 'Customize your theme', 'cherry-data-importer' ),
			'type'   => 'default',
			'target' => '_self',
			'url'    => admin_url( 'customize.php' ),
		),
	),
);
