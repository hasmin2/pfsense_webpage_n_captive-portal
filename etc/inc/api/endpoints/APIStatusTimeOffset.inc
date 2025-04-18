<?php
//   Copyright 2022 Jared Hendrickson
//
//   Licensed under the Apache License, Version 2.0 (the "License");
//   you may not use this file except in compliance with the License.
//   You may obtain a copy of the License at
//
//       http://www.apache.org/licenses/LICENSE-2.0
//
//   Unless required by applicable law or agreed to in writing, software
//   distributed under the License is distributed on an "AS IS" BASIS,
//   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//   See the License for the specific language governing permissions and
//   limitations under the License.

require_once("api/framework/APIEndpoint.inc");

class APIStatusTimeOffset extends APIEndpoint {
    public function __construct() {
        $this->url = "/api/v1/status/timeoffset";
    }
    protected function get() {
        return (new APIStatusGetTimeOffset())->call();
    }
    protected function post() {
        return (new APIStatusSetTimeOffset())->call();
    }
}