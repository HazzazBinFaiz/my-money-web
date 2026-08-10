<x-error-page :code="503"
              :title="__('Down for maintenance')"
              :message="__('We are making a quick change and will be back shortly.')"
              :hint="__('Try again in a few minutes.')" />
