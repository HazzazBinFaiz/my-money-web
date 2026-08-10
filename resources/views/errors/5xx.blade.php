{{-- Catch all for server errors without a page of their own. --}}
<x-error-page :code="$exception?->getStatusCode() ?? 500"
              :title="__('Something broke on our side')"
              :message="__('An error stopped this request from finishing. It has been logged and we will look at it.')"
              :hint="__('Your data is untouched: a request that fails is never half saved.')" />
