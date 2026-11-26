<div class="space-y-3">
    <p>Use <code>{{ $site->public_key }}</code> on <code>{{ $site->origin }}</code>. Choose one of the registered actions listed on this site.</p>
    <p>Set <code>data-mode="managed"</code> on the widget container to use this site's challenge mode. The verification button remains available if automatic verification cannot finish.</p>
    <p>Use a private verification key only on your backend. Submit the widget response to your backend with the form. Authorize the form operation only when verification succeeds for this site, origin, action and environment.</p>
    <p><a class="underline" href="{{ url('/docs') }}">Read the integration guide</a></p>
</div>
