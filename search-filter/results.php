<?php
/**
 * Template Name: Search Form for Study Spaces
 *
 * Created by PhpStorm.
 * User: David
 * Date: 2015-01-14
 * Time: 10:55 AM
 */
?>

    <style type="text/css">
        #gridcontainer {
            margin: 20px 0;
            width: 100%;
            float: left;
        }

        #gridcontainer h2 a {
            color: #77787a;
            font-size: 13px;
        }

        #gridcontainer .griditemleft {
            float: left;
            width: 278px;
            margin: 0 40px 40px 0;
        }

        #gridcontainer .griditemright {
            float: left;
            width: 278px;
        }

        #gridcontainer .postimage {
            margin: 0 0 10px 0;
            box-shadow: 3px 3px 3px #7C7C7C;
        }

        .gridresultcontainer {
            float: left;
        }
    </style>

<?php
if ($query->have_posts()) {
    ?>
    <div class="gridresultcontainer">
        Found <?php echo $query->found_posts; ?> Results<br/>
        Page <?php echo $query->query['paged']; ?> of <?php echo $query->max_num_pages; ?><br/>

        <div class="pagination">

            <div class="nav-previous"><?php next_posts_link('Older posts', $query->max_num_pages); ?></div>
            <div class="nav-next"><?php previous_posts_link('Newer posts'); ?></div>
            <?php
            // example code for using the wp_pagenavi plugin
            if (function_exists('wp_pagenavi')) {
                echo "<br />";
                wp_pagenavi(array('query' => $query));
            }
            ?>
        </div>
    </div>

    <div id="gridcontainer">
    <?php
    $counter = 1; // start counter
    $grids = 2; // grids per row
while ($query->have_posts()) {
    $query->the_post();
    // show the left hand side column
if ($counter == 1) : ?>
    <div class="griditemleft">
    <?php
    // show the right hand side column
    elseif ($counter == $grids) : ?>
    <div class="griditemright">
        <?php endif; ?>

        <div class="postimage">
            <a href="<?php the_permalink(); ?>"
               title="<?php the_title_attribute(); ?>"><?php the_post_thumbnail('category-thumbnail'); ?></a>
        </div>
        <h1><a href="<?php the_permalink(); ?>"
               title="<?php the_title_attribute(); ?>"><?php
                $terms = get_the_terms(get_the_ID(), LOCATIONS);
                if (!empty($terms)) {
                    $term = array_pop($terms);
                    echo $term->name . ' ';
                }
                the_title(); ?></a><br/>
        </h1>
    </div>

    <?php if ($counter == $grids) {
        $counter = 0;
        ?>
        <div class="clear"></div>

    <?php
    }
    $counter++;
}
    ?>
    </div>
    <div class="gridresultcontainer">
        Page <?php echo $query->query['paged']; ?> of <?php echo $query->max_num_pages; ?><br/>

        <div class="pagination">
            <div class="nav-previous"><?php next_posts_link('Older posts', $query->max_num_pages); ?></div>
            <div class="nav-next"><?php previous_posts_link('Newer posts'); ?></div>
            <?php
            // example code for using the wp_pagenavi plugin
            if (function_exists('wp_pagenavi')) {
                echo "<br />";
                wp_pagenavi(array('query' => $query));
            }
            ?>
        </div>
    </div>

<?php
} else {
    echo "No Results Found";
}
?>