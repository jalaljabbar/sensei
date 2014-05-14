<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Sensei Question Class
 *
 * All functionality pertaining to the questions post type in Sensei.
 *
 * @package WordPress
 * @subpackage Sensei
 * @category Core
 * @author WooThemes
 * @since 1.0.0
 *
 * TABLE OF CONTENTS
 *
 * - __construct()
 */
class WooThemes_Sensei_Question {
	public $token;
	public $meta_fields;

	/**
	 * Constructor.
	 * @since  1.0.0
	 */
	public function __construct () {
		$this->question_types = $this->question_types();
		$this->meta_fields = array( 'question_right_answer', 'question_wrong_answers' );
		if ( is_admin() ) {
			// Custom Write Panel Columns
			add_filter( 'manage_edit-question_columns', array( $this, 'add_column_headings' ), 10, 1 );
			add_action( 'manage_posts_custom_column', array( $this, 'add_column_data' ), 10, 2 );
			add_action( 'add_meta_boxes', array( $this, 'question_edit_panel_metabox' ), 10, 2 );

			add_action( 'save_post', array( $this, 'save_question' ), 10, 1 );
		} // End If Statement
	} // End __construct()

	public function question_types() {
		$types = array(
			'multiple-choice' => 'Multiple Choice',
			'boolean' => 'True/False',
			'gap-fill' => 'Gap Fill',
			'single-line' => 'Single Line',
			'multi-line' => 'Multi Line',
			'file-upload' => 'File Upload',
		);

		return apply_filters( 'sensei_question_types', $types );
	}

	/**
	 * Add column headings to the "lesson" post list screen.
	 * @access public
	 * @since  1.3.0
	 * @param  array $defaults
	 * @return array $new_columns
	 */
	public function add_column_headings ( $defaults ) {
		$new_columns['cb'] = '<input type="checkbox" />';
		// $new_columns['id'] = __( 'ID' );
		$new_columns['title'] = _x( 'Question', 'column name', 'woothemes-sensei' );
		$new_columns['question-type'] = _x( 'Type', 'column name', 'woothemes-sensei' );
		$new_columns['question-category'] = _x( 'Categories', 'column name', 'woothemes-sensei' );
		if ( isset( $defaults['date'] ) ) {
			$new_columns['date'] = $defaults['date'];
		}

		return $new_columns;
	} // End add_column_headings()

	/**
	 * Add data for our newly-added custom columns.
	 * @access public
	 * @since  1.3.0
	 * @param  string $column_name
	 * @param  int $id
	 * @return void
	 */
	public function add_column_data ( $column_name, $id ) {
		global $wpdb, $post;

		switch ( $column_name ) {
			case 'id':
				echo $id;
			break;
			case 'question-type':
				$output = get_the_term_list( $id, 'question-type', '', ', ', '' );
				if ( ! $output ) {
					$output = '&mdash;';
				} // End If Statement
				$question_type_orig = array(	'>boolean<',
												'>multiple-choice<',
												'>gap-fill<',
												'>multi-line<',
												'>single-line<',
												'>file-upload<'
										 );
				$question_type_replace = array(	'>True/False<',
												'>Multiple Choice<',
												'>Gap Fill<',
												'>Multi Line<',
												'>Single Line<',
												'>File Upload<'
										 );
				echo strip_tags( str_replace( $question_type_orig, $question_type_replace, $output ) );
			break;
			case 'question-category':
				$output = strip_tags( get_the_term_list( $id, 'question-category', '', ', ', '' ) );
				if( ! $output ) {
					$output = '&mdash;';
				}
				echo $output;
			break;
			default:
			break;
		}
	} // End add_column_data()

	public function question_edit_panel_metabox( $post_type, $post ) {
		if( 'question' == $post_type ) {

			$metabox_title = __( 'Question', 'woothemes-sensei' );

			if( isset( $post->ID ) ) {
				$question_type = '';
				$question_types = wp_get_post_terms( $post->ID, 'question-type', array( 'fields' => 'names' ) );
				if ( isset( $question_types[0] ) && '' != $question_types[0] ) {
					$question_type = $question_types[0];
				} // End If Statement

				if( $question_type ) {
					$type = $this->question_types[ $question_type ];
					if( $type ) {
						$metabox_title = $type;
					}
				}
			}
			add_meta_box( 'question-edit-panel', $metabox_title, array( $this, 'question_edit_panel' ), 'question', 'normal', 'high' );
		}
	}

	public function question_edit_panel() {
		global $woothemes_sensei, $post, $pagenow;

		add_action( 'admin_enqueue_scripts', array( $woothemes_sensei->post_types->lesson, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $woothemes_sensei->post_types->lesson, 'enqueue_styles' ) );

		$html = '';

		$before = '<div id="lesson-quiz" class="single-question"><div id="add-question-main">';
		$after = '';
		$question_type = '';

		if( 'post-new.php' == $pagenow ) {
			$question_id = 0;
			$before .= '<div id="add-question-actions">';
			$after .= '</div>';
		} else {
			$question_id = $post->ID;
			$before .= '<div id="add-question-metadata"><table class="widefat">';
			$after .= '</table></div>';

			$question_types = wp_get_post_terms( $question_id, 'question-type', array( 'fields' => 'names' ) );
			if ( isset( $question_types[0] ) && '' != $question_types[0] ) {
				$question_type = $question_types[0];
			} // End If Statement
		}

		$after .= '</div></div>';

		$html .= $before;
		$html .= $woothemes_sensei->post_types->lesson->quiz_panel_question( $question_type, 0, $question_id, 'question' );
		$html .= $after;

		echo $html;
	}

	public function save_question( $post_id = 0 ) {
		global $woothemes_sensei;

		if( ! isset( $_POST['post_type'] ) ) return;

		$data = $_POST;

		if ( 'question' != $data['post_type'] ) return;

		$data['quiz_id'] = 0;
		$data['question_id'] = $post_id;

		if ( ! wp_is_post_revision( $post_id ) ){

			// Unhook function to prevent infinite loops
			remove_action( 'save_post', array( $this, 'save_question' ) );

			// Update question data
			$question_id = $woothemes_sensei->post_types->lesson->lesson_save_question( $data, 'question' );

			// Re-hook same function
			add_action( 'save_post', array( $this, 'save_question' ) );
		}

		return;
	}

} // End Class
?>