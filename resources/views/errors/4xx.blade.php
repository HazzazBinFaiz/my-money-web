{{-- Catch all for client errors without a page of their own. --}}
<x-error-page :code="$exception?->getStatusCode() ?? 400"
              :title="__('That request could not be handled')"
              :message="__('Something about the request was not right, so it was turned away.')"
              :hint="__('Check the address and try again.')" />
