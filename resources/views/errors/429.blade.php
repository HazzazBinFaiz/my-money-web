<x-error-page :code="429"
              :title="__('Too many requests')"
              :message="__('You have sent quite a few requests in a short time.')"
              :hint="__('Wait a minute or so, then try again.')" />
