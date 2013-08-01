<?php
/**
 * The Template for displaying all single posts.
 *
 * @package WordPress
 * @subpackage Twenty_Twelve
 * @since Twenty Twelve 1.0
 */

get_header(); ?>

	<div id="primary" class="site-content">
		<div id="content" role="main">

			<?php while ( have_posts() ) : the_post(); ?>
                                  <?php if('gallery' == get_post_type()) : ?> 
                               <div id="gallery_single" >                                                               
                                  <?php echo do_shortcode("[gallery link='file']"); ?>
                                </div><!-- #gallery_single -->
                           <?php else : ?>
	                  <?php get_template_part( 'content', get_post_format() ); ?>
                              <?php endif; // get_post_type() ?>

			<?php endwhile; // end of the loop. ?>
                 
		</div><!-- #content -->

         <div id="previous_page_link">       
           
       
                  <a href="javascript:history.back();">« Return to previous page</a>
          
         </div>

	</div><!-- #primary -->

<?php get_sidebar(); ?>
</div><!-- #main .wrapper -->
</div><!-- #page -->
<?php get_footer(); ?>
