<x-error-page :code="419"
              :title="__('Your session expired')"
              :message="__('The page sat idle long enough that the security token went stale.')"
              :hint="__('Reload the page and submit the form once more; nothing was saved.')" />
