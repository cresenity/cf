<?php

/**
 * Description of RedirectController
 */
class CController_RedirectController extends CController {
    /**
     * Invoke the controller method.
     *
     * @return CHttp_RedirectResponse
     */
    public function __invoke() {
        $request = CHTTP::request();
        $url = CRouting::urlGenerator();
        $parameters = c::collect($request->route()->parameters());

        $status = $parameters->get('status');

        $destination = $parameters->get('destination');

        $parameters->forget('status')->forget('destination');

        $route = (new CRouting_Route('GET', $destination, [
            'as' => 'cf_route_redirect_destination',
        ]))->bind($request);

        $parameters = $parameters->only(
            $route->getCompiled()->getPathVariables()
        )->toArray();

        $url = $url->toRoute($route, $parameters, false);

        if (!cstr::startsWith($destination, '/') && cstr::startsWith($url, '/')) {
            $url = cstr::after($url, '/');
        }

        return new CHTTP_RedirectResponse($url, $status);
    }
}
