<?php
/**
 * Created on 10 Mar 2021
 * Time Created	: 10:28:28
 *
 * @filesource	downscripts.blade.php
 *
 * @author		wisnuwidi@incodiy.com - 2021
 * @copyright	wisnuwidi
 * @email		wisnuwidi@incodiy.com
 */

$scripts = [];
$scripts['bottom_first'] = [];
if (!empty($components->template->scripts['js']['bottom_first'])) $scripts['bottom_first'] = $components->template->scripts['js']['bottom_first'];
$scripts['bottom']       = [];
if (!empty($components->template->scripts['js']['bottom'])) $scripts['bottom'] = $components->template->scripts['js']['bottom'];
$scripts['bottom_last']  = [];
if (!empty($components->template->scripts['js']['bottom_last'])) $scripts['bottom_last'] = $components->template->scripts['js']['bottom_last'];
?>
	<!-- JS -->
	@foreach ($scripts['bottom_first'] as $script)
	{!! $script->html !!}
	@endforeach
    
	@foreach ($scripts['bottom'] as $script)
	{!! $script->html !!}
	@endforeach
    
	@foreach ($scripts['bottom_last'] as $script)
	{!! $script->html !!}
	@endforeach

	<!-- CSRF Token Fallback and AJAX Setup -->
	<script type="text/javascript">
	$(document).ready(function() {
	    // Ensure CSRF token is available for AJAX requests
	    var csrfToken = $('meta[name="csrf-token"]').attr('content');
	    
	    // Fallback: Get CSRF token via AJAX if meta tag is missing
	    if (!csrfToken) {
	        console.warn('🚨 CSRF meta tag missing - fetching token via AJAX...');
	        
	        $.get('/csrf-token', function(data) {
	            if (data && data.token) {
	                $('head').append('<meta name="csrf-token" content="' + data.token + '">');
	                console.log('✅ CSRF token added via fallback:', data.token);
	                
	                // Setup global AJAX CSRF token
	                $.ajaxSetup({
	                    headers: {
	                        'X-CSRF-TOKEN': data.token
	                    }
	                });
	            }
	        }).fail(function() {
	            console.error('❌ Could not retrieve CSRF token via fallback');
	        });
	    } else {
	        console.log('✅ CSRF token found in meta tag:', csrfToken);
	        
	        // Setup global AJAX CSRF token
	        $.ajaxSetup({
	            headers: {
	                'X-CSRF-TOKEN': csrfToken
	            }
	        });
	    }
	});
	</script>