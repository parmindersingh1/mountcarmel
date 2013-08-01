<?php
/**
 * The default template for displaying content. Used for both single and index/archive/search.
 *
 * @package WordPress
 * @subpackage Twenty_Twelve
 * @since Twenty Twelve 1.0
 */
?>    
 <?php if ( is_single() ) : ?>
			<?php else : ?>
			<div class="entry-content">  			  
    			<ul>
			<?php endif; // is_single() ?>  
     
	  
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php if ( is_sticky() && is_home() && ! is_paged() ) : ?>
		<div class="featured-post">
			<?php _e( 'Featured post', 'twentytwelve' ); ?>
		</div>
		<?php endif; ?>
		<header class="entry-header">
			
			<?php if ( is_single() ) : ?>
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<?php else : ?>
			 <li><a  title="<?php printf(__('Permanent Link to %s', 'framework'), get_the_title()); ?>" rel="bookmark" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
			<?php endif; // is_single() ?>
			<?php the_post_thumbnail(); ?>
		</header><!-- .entry-header -->

		<?php if ( is_search() ) : // Only display Excerpts for Search ?>
		<div class="entry-summary">
			<?php the_excerpt(); ?>
		</div><!-- .entry-summary -->
		<?php else : ?>
         
                <?php if ( is_single() ) : ?>
		<div class="entry-content">
		<?php the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'twentytwelve' ) ); ?>
			<?php wp_link_pages( array( 'before' => '<div class="page-links">' . __( 'Pages:', 'twentytwelve' ), 'after' => '</div>' ) ); ?>
		</div><!-- .entry-content -->
               <?php endif; // is_single() ?>
		<?php endif; ?>		
	</article><!-- #post -->
<?php if ( is_single() ) : ?>
<?php else : ?>
</ul>
</div >    
<?php endif; // is_single() ?>    
