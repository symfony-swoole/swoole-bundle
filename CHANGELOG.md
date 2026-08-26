# [0.33.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.32.0...v0.33.0) (2026-08-26)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.32.0...v0.33.0)

### Bug Fixes

* **coroutines:** command resolver fix for commands runninng in task handler + openswoolle/core info ([#373](https://github.com/symfony-swoole/swoole-bundle/issues/373)) ([5506d63](https://github.com/symfony-swoole/swoole-bundle/commit/5506d63a14fc7e56b250b64d6f771efc8df76c11))
* **coroutines:** constraint validator factory prepared for coroutine usage ([#382](https://github.com/symfony-swoole/swoole-bundle/issues/382)) ([cfd7dd1](https://github.com/symfony-swoole/swoole-bundle/commit/cfd7dd1bf91654c37ae1643fdc8c2c50b0d1d654))
* **coroutines:** fixed debug mode for security auth checker and twig profilers ([#367](https://github.com/symfony-swoole/swoole-bundle/issues/367)) ([483b6c9](https://github.com/symfony-swoole/swoole-bundle/commit/483b6c97808fd9702d6eac83fd26f17de5380a79))
* **coroutines:** fixed twg extension to work with coroutines ([#366](https://github.com/symfony-swoole/swoole-bundle/issues/366)) ([ba5eedb](https://github.com/symfony-swoole/swoole-bundle/commit/ba5eedb730ada68cff52453d7617688872fd0e8f))
* **coroutines:** form processor and twig renderer now work with coroutines ([#370](https://github.com/symfony-swoole/swoole-bundle/issues/370)) ([1dfcc60](https://github.com/symfony-swoole/swoole-bundle/commit/1dfcc60b1bc01d3f0d24ca605ba704d3fe109e03))
* **coroutines:** http client tracer made compatible with coroutines ([#371](https://github.com/symfony-swoole/swoole-bundle/issues/371)) ([86e9469](https://github.com/symfony-swoole/swoole-bundle/commit/86e946932e2c953dcdeee4bb8e7648fcedd54126))
* **coroutines:** poolable email validator for coroutine usage ([#386](https://github.com/symfony-swoole/swoole-bundle/issues/386)) ([d8bb737](https://github.com/symfony-swoole/swoole-bundle/commit/d8bb7371adac9a7e9f0de49596f35c595090b4ba))
* **coroutines:** pooling for mailer transports for coroutine usage ([#384](https://github.com/symfony-swoole/swoole-bundle/issues/384)) ([1af6cc3](https://github.com/symfony-swoole/swoole-bundle/commit/1af6cc3401e3feb8db8586477bc09f21bbc66309))
* **coroutines:** pooling of messenger transports ([#374](https://github.com/symfony-swoole/swoole-bundle/issues/374)) ([0d67669](https://github.com/symfony-swoole/swoole-bundle/commit/0d67669d606b353960d35159d331a37e50d18907))
* **coroutines:** unmanaged factory instantiation with locking ([#385](https://github.com/symfony-swoole/swoole-bundle/issues/385)) ([822eb6d](https://github.com/symfony-swoole/swoole-bundle/commit/822eb6d86ca7f042b854d5e96b29bab6d780966b))
* **hmr:** hmr timer clearing on process restart/exit ([#377](https://github.com/symfony-swoole/swoole-bundle/issues/377)) ([5ee2618](https://github.com/symfony-swoole/swoole-bundle/commit/5ee26182e434aa12192a9e1d858738d9399a140b))
* **messenger:** added specialized service resetter when running with coroutines supporting kernel ([#372](https://github.com/symfony-swoole/swoole-bundle/issues/372)) ([8f26d7f](https://github.com/symfony-swoole/swoole-bundle/commit/8f26d7fd40cdc5b6316612456d4b564be07e595a))
* **task_worker_commands:** fixed process reload race conditions ([#376](https://github.com/symfony-swoole/swoole-bundle/issues/376)) ([6577cd7](https://github.com/symfony-swoole/swoole-bundle/commit/6577cd700a5eae318b332c864515d94e7580ac91))
* **trusted_proxies:** fixed trusted proxies input processing + updated documentation for bundle commands ([#381](https://github.com/symfony-swoole/swoole-bundle/issues/381)) ([8c96325](https://github.com/symfony-swoole/swoole-bundle/commit/8c96325cecbfc48dc19d562970703a09f25609c8))


### Features

* **config:** add dispatch_mode paramenter management ([#387](https://github.com/symfony-swoole/swoole-bundle/issues/387)) ([ef44398](https://github.com/symfony-swoole/swoole-bundle/commit/ef44398f7d8f6929960ddc60001c2765b8269084))
* **coroutines:** debug:container command shows service pools properly ([#380](https://github.com/symfony-swoole/swoole-bundle/issues/380)) ([55e151f](https://github.com/symfony-swoole/swoole-bundle/commit/55e151f2c1d85f24c795e3f8a8f6bb1155900ac3))
* **coroutines:** swoole:debug:service-pools command added to show all poooled services ([#379](https://github.com/symfony-swoole/swoole-bundle/issues/379)) ([1015acb](https://github.com/symfony-swoole/swoole-bundle/commit/1015acb2e0bac042927807c93205505871b973a7))
* **file_watch:** restart server completely with cache:clear if needed ([#383](https://github.com/symfony-swoole/swoole-bundle/issues/383)) ([b37a9e8](https://github.com/symfony-swoole/swoole-bundle/commit/b37a9e868402f4745e1fcfe24ab1ae38193ef4d0))
* **multi_worker:** experimental ability to run multiple workers in a single open/swoole deployable unit ([#368](https://github.com/symfony-swoole/swoole-bundle/issues/368)) ([92c8f7a](https://github.com/symfony-swoole/swoole-bundle/commit/92c8f7aa2e06fe7356eb3d0eaac47c1a78b0965b))

# [0.32.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.31.0...v0.32.0) (2026-08-13)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.31.0...v0.32.0)

### Bug Fixes

* **coroutines:** added coroutine safe dbal optimized connection alive keeper ([#342](https://github.com/symfony-swoole/swoole-bundle/issues/342)) ([0383fda](https://github.com/symfony-swoole/swoole-bundle/commit/0383fda305cf6fe6bb6e371fc3de613a6a55866a))
* **coroutines:** default caching works with coroutines again ([#348](https://github.com/symfony-swoole/swoole-bundle/issues/348)) ([38b3cab](https://github.com/symfony-swoole/swoole-bundle/commit/38b3cab566ce768456bb138331a25b1cc4d5481b))
* **coroutines:** fixed coroutine race conditions in twig and traceable authenticator ([#357](https://github.com/symfony-swoole/swoole-bundle/issues/357)) ([51f3d79](https://github.com/symfony-swoole/swoole-bundle/commit/51f3d792f4393efdc4f57ae5447ea99ca98c2ffa))
* **coroutines:** fixed traceable message bus for coroutine workloads ([#361](https://github.com/symfony-swoole/swoole-bundle/issues/361)) ([fd30263](https://github.com/symfony-swoole/swoole-bundle/commit/fd302635acaef3570d2725e79c84a09dca2ed5bd))
* **coroutines:** fixes for default system.cache and for loggers ([#347](https://github.com/symfony-swoole/swoole-bundle/issues/347)) ([8dadaf2](https://github.com/symfony-swoole/swoole-bundle/commit/8dadaf2ecfafa26302434009b294a72d33e787f0))
* **coroutines:** lazy firewall context listener made usable with coroutines ([#349](https://github.com/symfony-swoole/swoole-bundle/issues/349)) ([305af93](https://github.com/symfony-swoole/swoole-bundle/commit/305af93f9853cf9540c473a4b62429b2c5b9c91c))
* **coroutines:** proxification for security access manager - fixed for debug mode ([#346](https://github.com/symfony-swoole/swoole-bundle/issues/346)) ([1f6a9b4](https://github.com/symfony-swoole/swoole-bundle/commit/1f6a9b428a0adc864d246c6a28288f53b21ea07b))
* **coroutines:** proxification for security access manager ([#345](https://github.com/symfony-swoole/swoole-bundle/issues/345)) ([d933883](https://github.com/symfony-swoole/swoole-bundle/commit/d9338833755c1efa91a969b6dfb62d027e167712))
* **coroutines:** proxification of security and translation related services ([#354](https://github.com/symfony-swoole/swoole-bundle/issues/354)) ([8c29cd7](https://github.com/symfony-swoole/swoole-bundle/commit/8c29cd752b7e533f4bf107cea31efb467737fa3f))
* **coroutines:** removed controller resolver container mutation on controllers ([#343](https://github.com/symfony-swoole/swoole-bundle/issues/343)) ([488132a](https://github.com/symfony-swoole/swoole-bundle/commit/488132a12e5e4fe66e5dbc2650dd4ad197f09156))
* **coroutines:** support for lazy services in di container with coroutines enabled ([#356](https://github.com/symfony-swoole/swoole-bundle/issues/356)) ([ff656d6](https://github.com/symfony-swoole/swoole-bundle/commit/ff656d649efc82f4192c4fc506d76c8b69fad68a))
* **doctrine:** debug data holder resetting fix ([#359](https://github.com/symfony-swoole/swoole-bundle/issues/359)) ([e485833](https://github.com/symfony-swoole/swoole-bundle/commit/e4858338d1ddac79d4755bdfdcfa903260c7859f))
* **error_handler:** fix for app boot with error handler when coroutines enabled ([#360](https://github.com/symfony-swoole/swoole-bundle/issues/360)) ([99d0130](https://github.com/symfony-swoole/swoole-bundle/commit/99d013042edaa803ffd537bf9448ee58b5287e93))
* **error_handler:** resetting error handler before each usage to avoid unexpected shutdowns ([#341](https://github.com/symfony-swoole/swoole-bundle/issues/341)) ([f08c9b2](https://github.com/symfony-swoole/swoole-bundle/commit/f08c9b26ace484bb94f635fb071493ef6baa8b84))
* **healthcheck:** prevent a stalled client from delaying liveness probes ([#344](https://github.com/symfony-swoole/swoole-bundle/issues/344)) ([51c9d70](https://github.com/symfony-swoole/swoole-bundle/commit/51c9d70e9b2a7fb90a4f057b705c65229d56f5eb))
* **server:watch:** watch crash recovery improvements ([#362](https://github.com/symfony-swoole/swoole-bundle/issues/362)) ([6e5ffe9](https://github.com/symfony-swoole/swoole-bundle/commit/6e5ffe9dafe80fdb75dceef386710013d39bd05a))
* **session:** fixed deserialization failure ([#358](https://github.com/symfony-swoole/swoole-bundle/issues/358)) ([27a38df](https://github.com/symfony-swoole/swoole-bundle/commit/27a38dfd1b99cdd8929855d6ea9ecbc30069f6d1))


### Features

* **config:** create pid_file only for daemon server ([#309](https://github.com/symfony-swoole/swoole-bundle/issues/309)) ([1983412](https://github.com/symfony-swoole/swoole-bundle/commit/198341248947d680a9f1e80b9a3e1d9dae794aae))
* **max_wait_time:** added max_wait_time open/swoole flag to fix flaky swoole tests ([#353](https://github.com/symfony-swoole/swoole-bundle/issues/353)) ([8ceda25](https://github.com/symfony-swoole/swoole-bundle/commit/8ceda25a9bd8d3b40b593ed5659e3bd2cf6c2ee9))

# [0.31.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.30.0...v0.31.0) (2026-08-03)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.30.0...v0.31.0)

### Bug Fixes

* **coroutines:** added unserialization logic to proxies to enable data collectors to work ([#324](https://github.com/symfony-swoole/swoole-bundle/issues/324)) ([327337e](https://github.com/symfony-swoole/swoole-bundle/commit/327337e6e372cbc7c1c2db3d8286cf55a9496ac2))
* **coroutines:** event dispatcher override fix ([#334](https://github.com/symfony-swoole/swoole-bundle/issues/334)) ([336621d](https://github.com/symfony-swoole/swoole-bundle/commit/336621d903111c6232eaf3f47fac5b4063d5e589))
* **coroutines:** event dispatcher proxification fix ([#325](https://github.com/symfony-swoole/swoole-bundle/issues/325)) ([4db3300](https://github.com/symfony-swoole/swoole-bundle/commit/4db3300a78cfa836d99b5ecd5518bfed3f580700))
* **coroutines:** fixed cache warmup when coroutines are enabled ([#329](https://github.com/symfony-swoole/swoole-bundle/issues/329)) ([5da8049](https://github.com/symfony-swoole/swoole-bundle/commit/5da80494af534d713eece69cbe493184813c2828))
* **coroutines:** proper resetting for kernel.reset services ([#335](https://github.com/symfony-swoole/swoole-bundle/issues/335)) ([1b96408](https://github.com/symfony-swoole/swoole-bundle/commit/1b96408e917d7bb27857aa0ac34a62c1b73330ee))
* **coroutines:** proxification of event dispatchers for firewalls ([#333](https://github.com/symfony-swoole/swoole-bundle/issues/333)) ([47a18fb](https://github.com/symfony-swoole/swoole-bundle/commit/47a18fb07a8828b06f534c2d4f216c4cffb29746))
* **router:** removed concurrency issues from sf router for coroutine usage ([#313](https://github.com/symfony-swoole/swoole-bundle/issues/313)) ([012e861](https://github.com/symfony-swoole/swoole-bundle/commit/012e861bca01b5e596fe994c33d54eb3e190331a))
* **router:** unit tests for locking sf router parts ([#314](https://github.com/symfony-swoole/swoole-bundle/issues/314)) ([2908670](https://github.com/symfony-swoole/swoole-bundle/commit/2908670a390beacb1e374df257ecf26678677a03))
* **tests:** pdo session tests cannot execute session gc because of deadlocks ([#323](https://github.com/symfony-swoole/swoole-bundle/issues/323)) ([14f29cb](https://github.com/symfony-swoole/swoole-bundle/commit/14f29cbdffa9222d10efa11ca25494f3734a09ce))


### Features

* **coroutines:** allow optional stateful services to skip proxification ([#331](https://github.com/symfony-swoole/swoole-bundle/issues/331)) ([9284477](https://github.com/symfony-swoole/swoole-bundle/commit/9284477c90e396428e0f4a899306ebcffc837c3b))
* **coroutines:** isolate error and exception handlers per coroutine ([#330](https://github.com/symfony-swoole/swoole-bundle/issues/330)) ([aeff098](https://github.com/symfony-swoole/swoole-bundle/commit/aeff09821a5f749d8c634096f8063fe61a866c12))
* **coroutines:** make Monolog stream handlers coroutine safe without locks ([#332](https://github.com/symfony-swoole/swoole-bundle/issues/332)) ([a657692](https://github.com/symfony-swoole/swoole-bundle/commit/a657692ba6209d80cb96bfb6d8025a7a3bbac45e))
* **healthcheck:** introduce separate process for server healthchecks ([#336](https://github.com/symfony-swoole/swoole-bundle/issues/336)) ([b7e3be7](https://github.com/symfony-swoole/swoole-bundle/commit/b7e3be79c3a97f3ea3b396e1094fd4ff09f55210))
* **hmr:** add polling-based reload mechanism ([#328](https://github.com/symfony-swoole/swoole-bundle/issues/328)) ([d3a57fd](https://github.com/symfony-swoole/swoole-bundle/commit/d3a57fd3ef3d4a6421cfee9b86a934a507166129))
* **proxies:** added ability to clone proxified objects for single use ([#322](https://github.com/symfony-swoole/swoole-bundle/issues/322)) ([abcb5db](https://github.com/symfony-swoole/swoole-bundle/commit/abcb5dbb56d4198bc242e7df75daaa736356ed4b))
* **session:** integration and coroutine support for session handler interfaces ([#318](https://github.com/symfony-swoole/swoole-bundle/issues/318)) ([2a5d25c](https://github.com/symfony-swoole/swoole-bundle/commit/2a5d25c713abd7976ee0ef456c58b5357219ba67))
* **session:** session handling refactor for swoole table session + better configurability ([#315](https://github.com/symfony-swoole/swoole-bundle/issues/315)) ([6ab87a5](https://github.com/symfony-swoole/swoole-bundle/commit/6ab87a5ae3093c450f228e30c7eae32726665c32))

# [0.30.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.29.0...v0.30.0) (2026-07-05)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.29.0...v0.30.0)

### Bug Fixes

* **container:** fix for container overriding code while cache:clear is being executed ([#299](https://github.com/symfony-swoole/swoole-bundle/issues/299)) ([dfb85df](https://github.com/symfony-swoole/swoole-bundle/commit/dfb85df6f86f3e7c088037e5072e24dfb8a31944))
* **coroutines:** event_dispatcher proxofication ([#300](https://github.com/symfony-swoole/swoole-bundle/issues/300)) ([cf2907b](https://github.com/symfony-swoole/swoole-bundle/commit/cf2907b561b78217054755c21397cbca7ca2eabc))


### Features

* Added extended kernel class detection ([#298](https://github.com/symfony-swoole/swoole-bundle/issues/298)) ([c320df7](https://github.com/symfony-swoole/swoole-bundle/commit/c320df77ed3e4a88864b3a33738346ec8dc6a6ac))

# [0.29.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.28.0...v0.29.0) (2026-06-19)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.28.0...v0.29.0)

### Features

* Added OpenSwoole EventLoop lag metrics ([#288](https://github.com/symfony-swoole/swoole-bundle/issues/288)) ([c6e36f5](https://github.com/symfony-swoole/swoole-bundle/commit/c6e36f540af404c68df4d616b2fcebb51ee82219))

# [0.28.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.27.1...v0.28.0) (2026-06-16)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.27.1...v0.28.0)

### Bug Fixes

* **code:** fix typos, duplicates, small improvements ([#260](https://github.com/symfony-swoole/swoole-bundle/issues/260)) ([782edb6](https://github.com/symfony-swoole/swoole-bundle/commit/782edb6709f93c0c6b6b58347150000c13de6db3))
* **coroutines:** fixed service resetting and removed as much concurrent data accesses as possible ([#279](https://github.com/symfony-swoole/swoole-bundle/issues/279)) ([2006df7](https://github.com/symfony-swoole/swoole-bundle/commit/2006df701b6a059e26090c2636f48fd25bf7cc54))
* **deps:** allow a wder ext-swoole version constraint in composer.json ([ef965cc](https://github.com/symfony-swoole/swoole-bundle/commit/ef965cc4fb6661ac1cd17c1c5f2054c622e78e8f))
* **file-server:** fix static file path traversal outside public path ([#269](https://github.com/symfony-swoole/swoole-bundle/issues/269)) ([c5d88e4](https://github.com/symfony-swoole/swoole-bundle/commit/c5d88e4ac53bf1c85d13c80988d0c704811bfc84))


### Features

* Added Swoole OpenMetrics formatter ([#282](https://github.com/symfony-swoole/swoole-bundle/issues/282)) ([8353353](https://github.com/symfony-swoole/swoole-bundle/commit/835335305fc55261db055c0a751eb320542f3abf))

## [0.27.1](https://github.com/symfony-swoole/swoole-bundle/compare/v0.27.0...v0.27.1) (2026-05-20)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.27.0...v0.27.1)

### Miscellaneous

* Minor fixes

# [0.27.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.26.0...v0.27.0) (2026-04-12)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.26.0...v0.27.0)

### Features

* **composer:** added suppport for symfony 8.0, removed support for symfony 6.4 ([#231](https://github.com/symfony-swoole/swoole-bundle/issues/231)) ([5e701f9](https://github.com/symfony-swoole/swoole-bundle/commit/5e701f9c07d2e495b96f224127bc9513780b5a9d))

# [0.26.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.25.1...v0.26.0) (2026-04-10)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.25.1...v0.26.0)

### Bug Fixes

* **proxifier:** add interface_exists in StatafulServicePass ([#223](https://github.com/symfony-swoole/swoole-bundle/issues/223)) ([fe6f2fd](https://github.com/symfony-swoole/swoole-bundle/commit/fe6f2fdabc4e7585b0173c1732a4fe5df435b312))


### Features

* **fiber_context:** enabled fiber context activation for swoole and openswoole, removed xdebug support hacks ([#227](https://github.com/symfony-swoole/swoole-bundle/issues/227)) ([43a853b](https://github.com/symfony-swoole/swoole-bundle/commit/43a853b65c89d625bcc1b87a56e36de87865b670))
* **iouring:** iouring support for swoole and openswoole ([#226](https://github.com/symfony-swoole/swoole-bundle/issues/226)) ([cde9007](https://github.com/symfony-swoole/swoole-bundle/commit/cde90072fda46b53fded940cd91927e381dce448))
* **php8.5:** upgrade to openswoole 26.2.0 ([#222](https://github.com/symfony-swoole/swoole-bundle/issues/222)) ([e5d95d6](https://github.com/symfony-swoole/swoole-bundle/commit/e5d95d69d5744b3846ea98e659253db5ece11a0b))
* **php8.5:** upgrade to php 8.5, symfony support for v7.4, removed support for 7.2 and 7.3, static analysis tools upgrade ([#220](https://github.com/symfony-swoole/swoole-bundle/issues/220)) ([65e316e](https://github.com/symfony-swoole/swoole-bundle/commit/65e316ea8e6fd409ece4eaf486e66e6eca10076e))
* **profiler:** full profiler support + service lifecycle refactor ([#224](https://github.com/symfony-swoole/swoole-bundle/issues/224)) ([c35a224](https://github.com/symfony-swoole/swoole-bundle/commit/c35a2241094a3090cc8e0d9f9e08f29d5da91a58))
* **swoole:** final upgrade to swoole 6.2 ([#225](https://github.com/symfony-swoole/swoole-bundle/issues/225)) ([7e30438](https://github.com/symfony-swoole/swoole-bundle/commit/7e3043836aab15b46e1091a07dc1e389bcaa6faa))

## [0.25.1](https://github.com/symfony-swoole/swoole-bundle/compare/v0.25.0...v0.25.1) (2025-11-30)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.25.0...v0.25.1)

### Bug Fixes

* **configuration:** prevent bundle from overwriting proxies/hosts when not defined ([4f6c747](https://github.com/symfony-swoole/swoole-bundle/commit/4f6c747c7536a9cace98bfd6f922122f57258dd8))

# [0.25.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.24.1...v0.25.0) (2025-08-14)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.24.1...v0.25.0)

### Bug Fixes

* **doctrine:** skip the BlockingProxyFactory override when using Doctrine ORM >= 3.4.0 with native lazy objects enabled ([ea76802](https://github.com/openswoole-bundle/openswoole-bundle/commit/ea7680201227bc1cc269a751647abc4aa0acc8b2))


### Features

* **http-compression:** add configuration options for HTTP compression and compression level ([f348c98](https://github.com/openswoole-bundle/openswoole-bundle/commit/f348c98b8d4d2af59d45d15466c5c8be673b2d5f))

## [0.24.1](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.24.0...v0.24.1) (2025-05-21)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.24.0...v0.24.1)

### Miscellaneous

* Minor fixes

# [0.24.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.23.0...v0.24.0) (2025-03-01)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.23.0...v0.24.0)

### Bug Fixes

* **blackfire:** minor fixes to balckfire monitoring feature ([d7c3a63](https://github.com/openswoole-bundle/openswoole-bundle/commit/d7c3a63f9e6467adc2f799de49e24a737b9231e9))
* **date-formatter:** add default IntlDateFormatter construct parameters ([32056a0](https://github.com/openswoole-bundle/openswoole-bundle/commit/32056a0b8623a36931b275bef743489c05d3d6b8))
* **deps:** removed dependency on forked upscale/swoole-blackfire ([9c75ea3](https://github.com/openswoole-bundle/openswoole-bundle/commit/9c75ea36e4aebee277f2b327b48aa82d5b2ea44d))
* **monolog:** removed old overridden Monolog StreamHandler versions ([84979a0](https://github.com/openswoole-bundle/openswoole-bundle/commit/84979a0b4f1365ffb6a16d6772ce9ee766e009d7))
* **php:** removed PHP 8.1 support, minumal supported version is PHP 8.2 ([f933bf9](https://github.com/openswoole-bundle/openswoole-bundle/commit/f933bf96d1f8ff156182029778257e6ca8a516ba))
* **proxifier:** fixed proxification of opotional resetters ([2e67726](https://github.com/openswoole-bundle/openswoole-bundle/commit/2e677268aedfc88114519854c3674d3e245ca0dd))
* **symfony:** removed Symfony 5.4 support, minimal supported version is 6.4 ([99f6bc8](https://github.com/openswoole-bundle/openswoole-bundle/commit/99f6bc85e94e216499edfe6837e1719e6097d4d5))
* **symfony:** removed Symfony 7.0 support, minimal supported version is 7.2 ([c6a225e](https://github.com/openswoole-bundle/openswoole-bundle/commit/c6a225e53d1f5714581d3ce9e5742caccd336ec2))


### Features

* **deps:** integrate resetter bundle ([f0b5f7a](https://github.com/openswoole-bundle/openswoole-bundle/commit/f0b5f7a67b9b230cf0eaf595515cf82a33451545))
* **deps:** support for blackfirephp-sdk v2.5 ([476b20d](https://github.com/openswoole-bundle/openswoole-bundle/commit/476b20d7cdbc12f4b2c9ea4f23951a90466d2176))
* **doctrine:** Support for Doctrine v3 ([c1e7790](https://github.com/openswoole-bundle/openswoole-bundle/commit/c1e779066f79caaab0337b08f178cbec53dd2246))
* **openswoole:** Support for OpenSwoole 25.2.0 ([9620f11](https://github.com/openswoole-bundle/openswoole-bundle/commit/9620f1127bc1752905c7486ac5a8fa3faeed5caa))
* **php:** Support for PHP 8.4 ([26f387b](https://github.com/openswoole-bundle/openswoole-bundle/commit/26f387b404f995808fe03cef56146dbe738b7218))
* **swoole:** added ability to override the upload_tmp_dir swoole setting ([4505c12](https://github.com/openswoole-bundle/openswoole-bundle/commit/4505c128933e3693ad60ce2f5fc22d95be208805))

# [0.23.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.22.1...v0.23.0) (2025-01-05)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.22.1...v0.23.0)

### Bug Fixes

* **configuration:** Prevent invalid array access when posix_getpwuid returns false ([73da504](https://github.com/openswoole-bundle/openswoole-bundle/commit/73da504abd1e4c5a6178837d81162c3b17506eae))


### Features

* **swoole:** swoole updated to v6.0.0 ([a0d349d](https://github.com/openswoole-bundle/openswoole-bundle/commit/a0d349d9e3243dcc1d25af7f999c7c52be65a69d))

## [0.22.1](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.22.0...v0.22.1) (2024-09-08)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.22.0...v0.22.1)

### Performance Improvements

* **access-log:** initialize IntlDateFormatter once based on icu format instead of slow static StrftimeToICUFormatMap::mapStrftimeToICU() ([466146d](https://github.com/openswoole-bundle/openswoole-bundle/commit/466146d76ba1e7c294df5e0af59cd581329cc9c1))

# [0.22.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.21.2...v0.22.0) (2024-08-22)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.21.2...v0.22.0)

### Features

* **settings:** allow set user and group for worker and task worker child processes ([210c143](https://github.com/openswoole-bundle/openswoole-bundle/commit/210c143f2e933b5c1c47ecfc4f249ee8aacd5532))

## [0.21.2](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.21.1...v0.21.2) (2024-04-18)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.21.1...v0.21.2)

### Bug Fixes

* **access-log:** fixed log format %D, the time taken to serve the request, in microseconds ([94d9314](https://github.com/openswoole-bundle/openswoole-bundle/commit/94d931494e92b55cfff12fa245f21ca742a5ff32))
* **access-log:** set default timezone from php since $timezone parameter and the current timezone are ignored when the $datetime parameter is a UNIX timestamp (starts with @) ([8a21b1d](https://github.com/openswoole-bundle/openswoole-bundle/commit/8a21b1d2f1f916be159457e4c2b596d1cb1c40eb))

## [0.21.1](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.21.0...v0.21.1) (2024-03-18)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.21.0...v0.21.1)

### Bug Fixes

* **command:** close console output after server start to prevent bad file descriptor in docker ([9197522](https://github.com/openswoole-bundle/openswoole-bundle/commit/9197522d245668f7d801042558ef4de51406d9e8))
* **phpstan:** suppress phpstan for output verbosity because OutputInterface constants don't work properly ([7c01db5](https://github.com/openswoole-bundle/openswoole-bundle/commit/7c01db5d8eafb37ff602a1a874b6640eb35e75a9))

# [0.21.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.20.0...v0.21.0) (2024-02-12)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.20.0...v0.21.0)

### Features

* **codestyle:** added phpcs to php tooling, added and applied new PER coding style, tweaked php-cs-fixer configuration accordingly ([647f7f5](https://github.com/openswoole-bundle/openswoole-bundle/commit/647f7f54c61c92869dfcffec6e8f7c6dd94ef3be))
* **symfony:** added support for Symfony 7.0 ([c0ea0d4](https://github.com/openswoole-bundle/openswoole-bundle/commit/c0ea0d416f21682b5fd3782976ba51153ae64dc9))

# [0.20.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.19.0...v0.20.0) (2023-12-30)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.19.0...v0.20.0)

### Bug Fixes

* **signals:** disabled symfony signals handling to be able to terminate the server with simple ctrl+c ([88b8cba](https://github.com/openswoole-bundle/openswoole-bundle/commit/88b8cba281543e867ddda28d5642791d4efb777c))


### Features

* **symfony:** added support for Symfony 6.4, dropped support for Symfony 6.3 ([327f06c](https://github.com/openswoole-bundle/openswoole-bundle/commit/327f06c190d1063930a527c137daef645025ed02))

# [0.19.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.18.0...v0.19.0) (2023-12-22)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.18.0...v0.19.0)

### Features

* **php:** added support for PHP 8.3, removed support for PHP 8.0 ([3be4865](https://github.com/openswoole-bundle/openswoole-bundle/commit/3be4865f439cf0a5c5502146f47b0a66b5fce723))

# [0.18.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.17.0...v0.18.0) (2023-12-17)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.17.0...v0.18.0)

### Features

* **openswoole:** added support for openswoole 22.0.0, dropped support for openswoole 4 ([cbe8dd0](https://github.com/openswoole-bundle/openswoole-bundle/commit/cbe8dd06e7e6941ce5a9ed78bf9d0200a47094a7))

# [0.17.0](https://github.com/openswoole-bundle/openswoole-bundle/compare/v0.16.0...v0.17.0) (2023-11-26)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.16.0...v0.17.0)

### Features

* **organisation:** renamed Github organisation to symfony-swoole, Packagist organisation to swoole-bundle, Docker Hub organisation to wymfonywithswoole ([12a965f](https://github.com/openswoole-bundle/openswoole-bundle/commit/12a965f0a436b3e6ad1a4f82a5cef3073d26dc86))
* **swoole-ext:** support for Swoole 5.1.1 ([a49dd69](https://github.com/openswoole-bundle/openswoole-bundle/commit/a49dd69111b07c8d111a48fed80ec460e0e785fd))
* **swoole:** added support for swoole 5.1 ([d82b686](https://github.com/openswoole-bundle/openswoole-bundle/commit/d82b686ce9b18c1a3a860b50cfa1ca8e51049ddf))

# [0.16.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.15.0...v0.16.0) (2023-11-19)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.15.0...v0.16.0)

### Bug Fixes

* **circleci:** fixed release workflow, removed docker bake approval ([f1086f4](https://github.com/symfony-swoole/swoole-bundle/commit/f1086f47208d1f2c2aa31272d194f97b1e22f81e))


### Features

* **swoole:** added swoole support for swoole 4.8.13 ([e827e99](https://github.com/symfony-swoole/swoole-bundle/commit/e827e9909bc734798873c5d2d37cb7551773b926))

# [0.15.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.14.0...v0.15.0) (2023-11-11)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.14.0...v0.15.0)

### Bug Fixes

* **coding-standards:** changes according to update of php-cs-fixer to 3.35.1 ([3dd9143](https://github.com/symfony-swoole/swoole-bundle/commit/3dd914363cb328fd8acd36fd131edbb6732c850a))


### Features

* **ci:** added matrix to php builds, to simplify upgrades and extension of jobs, PHP support lock to 8.0-8.2, Symfony support lock to 5.4.22+ and 6.3 ([cc0f4b6](https://github.com/symfony-swoole/swoole-bundle/commit/cc0f4b604540d20fd95e44c194ccd2cf13e8fc3b))

# [0.14.0](https://github.com/symfony-swoole/swoole-bundle/compare/v0.13.1...v0.14.0) (2023-10-14)

[Full changelog](https://github.com/symfony-swoole/swoole-bundle/compare/v0.13.1...v0.14.0)

### Bug Fixes

* **blackfire:** removed blackfire mock because it did not work with z-engine hacks on every PHP version ([9a8044f](https://github.com/symfony-swoole/swoole-bundle/commit/9a8044fdd0d1fd5aff271ca07a61833cffe5de6d))
* **build:** added forgotten dockerhub context to trusted swoole-bundle-80-code-coverage job run ([45fc9ce](https://github.com/symfony-swoole/swoole-bundle/commit/45fc9ce703ee87b250fc4ddbbfbe9b416dd2e887))
* **ci:** do not support openswoole 22 yet ([8cf9cd6](https://github.com/symfony-swoole/swoole-bundle/commit/8cf9cd62c700ce177dbc0eb9bed4f061df1c88e6))
* **ci:** fixed memory limit overflow in merge-code-coverage ([0ccbb71](https://github.com/symfony-swoole/swoole-bundle/commit/0ccbb71da4632f8c7e72fb5cb544842755d51187))
* **coroutine-tests:** fixed coroutine tests that behave differently with code coverage enabled ([dcd5515](https://github.com/symfony-swoole/swoole-bundle/commit/dcd5515a2eddb2e6b7549b2dbda335c032d24d09))
* **coroutines:** added custom proxy type instead of lazy loading value holder to get rid of shared lazy tmp object access ([df3da24](https://github.com/symfony-swoole/swoole-bundle/commit/df3da242b1da8cbeb6cd866fb3782c089e06e4ca))
* **coroutines:** added entity manager resetter, which is decoupled from the stability checker, so it can be overridden easily ([e8f5c7b](https://github.com/symfony-swoole/swoole-bundle/commit/e8f5c7b1316d00421a822895450cc2aedeea17bd))
* **coroutines:** added failsafe for accidental container overrides while running tests ([62afa05](https://github.com/symfony-swoole/swoole-bundle/commit/62afa0539a9f76b99ac85f10986d7505a8755e6d))
* **coroutines:** added global exclusive single coroutine access to each container operation, so no deadlocks occurence is possible ([6d0611b](https://github.com/symfony-swoole/swoole-bundle/commit/6d0611bfbbf9d6c4c766c7053e1e2204da108226))
* **coroutines:** container modifier load method override fix for generated production service container ([522e5ba](https://github.com/symfony-swoole/swoole-bundle/commit/522e5ba1bdea1743582312395e59e13b9bc3a882))
* **coroutines:** coroutine proxifier correctly assigns resetter from stateful service tag ([80d2c60](https://github.com/symfony-swoole/swoole-bundle/commit/80d2c60308f3fdb961790b60be103572526f384d))
* **coroutines:** disabled proxification and resetting of doctrine registry, because of changed resetting mechanism ([84c0a0e](https://github.com/symfony-swoole/swoole-bundle/commit/84c0a0ebe567905674bb3b572d5f1f79277ec531))
* **coroutines:** enforcing resettable services reset on each request, even before their instantiation is really needed ([a42558b](https://github.com/symfony-swoole/swoole-bundle/commit/a42558bb8669d828448b64d588d125fe9d4a0251))
* **coroutines:** fix for proxy file locator, which needs an existing directory path as input ([e59fe88](https://github.com/symfony-swoole/swoole-bundle/commit/e59fe8834e4c7911a9f77fd5547a60b189f9ae15))
* **coroutines:** fixed advanced service instantiation in container while using coroutines (blocking needs to be applied to be sure that no problems arise) ([ba18802](https://github.com/symfony-swoole/swoole-bundle/commit/ba18802d598549c4da11d802d7092344d682bc1f))
* **coroutines:** got rid of method overriding using z-engine ([79a5a0a](https://github.com/symfony-swoole/swoole-bundle/commit/79a5a0a14980b2e06d4704f7fef1e0f53a417677))
* **coroutines:** if a stateful service is not shared, its service pool has to be added to the service pool container on instantiation, so the instances can be released on request end ([993eaf8](https://github.com/symfony-swoole/swoole-bundle/commit/993eaf874dd051a9dafdb01d9e117e09edab8c27))
* **coroutines:** implemented container inlien factory methods overrides (using z-engine), which activates blocking while creating services ([dfe0120](https://github.com/symfony-swoole/swoole-bundle/commit/dfe0120a1ac8210efa1add0bb4bfe97ce49acd1c))
* **coroutines:** one time initialised lazy proxies will be always returned on context switch, even when the initialisation did not finish ([2a30be7](https://github.com/symfony-swoole/swoole-bundle/commit/2a30be731a35301d13c2953e4a6deccc3cddbffb))
* **coroutines:** prod container generation imlemented using generated container class instead of z-engine because of segmentation faults ([ff20a76](https://github.com/symfony-swoole/swoole-bundle/commit/ff20a76b3cf4e7bc0bcb13b9ab2be51e7cce9570))
* **coroutines:** proxified service definition needs to be modified after proxy definition creation, so only original values can be applied to proxy ([1079f30](https://github.com/symfony-swoole/swoole-bundle/commit/1079f3000efbea43ff7dbddf315cc37ea390fb33))
* **coroutines:** service resetter reevaluation needs to run  after RemoveUnusedDefinitionsPass so the resetter can get only existing services and instantiate them on first usage ([4972ff3](https://github.com/symfony-swoole/swoole-bundle/commit/4972ff31a70652ae197b25e52f0d32ca6f3f16b1))
* **dependabot:** allow only one open pull request, so workflows do not have to be rebased after dependabot PRs ([47d6dc4](https://github.com/symfony-swoole/swoole-bundle/commit/47d6dc4479e5dfef5ab75f71556500a7d270581e))
* Get rid of deprecation notice for StreamedResponseListener ([973fcfb](https://github.com/symfony-swoole/swoole-bundle/commit/973fcfb45ffde99411d5a4811a5a647acd45eab4))
* **performance:** wrapped entity managers are eager by default, the service pool proxy is the lazy layer ([7ccbebe](https://github.com/symfony-swoole/swoole-bundle/commit/7ccbebe54b4e12dbc060687567590e0d184e38f6))
* **php-cs-fixer:** returned back the blank_line_between_import_groups rule ([b2c6480](https://github.com/symfony-swoole/swoole-bundle/commit/b2c64804c2242c287b77107e3c8738edc12d49ac))
* **phpcsfixer:** disabled the blank_line_between_import_groups rule ([9b4385f](https://github.com/symfony-swoole/swoole-bundle/commit/9b4385fd331da69b380d728b58acbdf91c1f0623))
* **phpstan:** removed phpstan ignore annotation in CallableBootManagerFactory because of phpstan update ([db4b619](https://github.com/symfony-swoole/swoole-bundle/commit/db4b61929bb3503e8ace6f0b8efbca91b92e635c))
* **proxies:** removed SF proxy factory because it is deleted in SF 6.2 ([22e2e9c](https://github.com/symfony-swoole/swoole-bundle/commit/22e2e9c822a700fb27bb62d31a71cf8f133345a8))
* **proxifier:** coroutine proxifier - proxified services' service pools are shared or non-shared based on the proxified service shared flag, together with the service proxy ([c4c6006](https://github.com/symfony-swoole/swoole-bundle/commit/c4c6006ff6d6fa7571061911a15d38d26e8b65d3))
* **releaser:** added gpg folder mount to the releaser script v0.4.0 ([0228fd3](https://github.com/symfony-swoole/swoole-bundle/commit/0228fd35d599841dd7c9d13ebe020ded583b4b39))
* **sf63:** added override for monolog StreamHandler + fixed sf 6.3 error handling ([cf35c83](https://github.com/symfony-swoole/swoole-bundle/commit/cf35c83113516a41c1f4751cfaacd10b3fda46e3))
* **shutdown:** default SF signal handlers were overriden so no exit is called on app shutdown ([30a71f8](https://github.com/symfony-swoole/swoole-bundle/commit/30a71f886dcf90e61ab3c23e50db2f4d78803ab5))
* **styles:** code styles fix by phpcsfixer ([7dfc682](https://github.com/symfony-swoole/swoole-bundle/commit/7dfc682c12d2c7e5bc015f56f0a6d466260f5178))
* **swhutdown:** last shutdown fix should only work on pcntl enabled setups ([9cd2417](https://github.com/symfony-swoole/swoole-bundle/commit/9cd2417b9307ea886146a06291ad6b242a4f0008))
* **tests:** fix for coverage tests - higher waiting values needed in some specific cases ([b10bd60](https://github.com/symfony-swoole/swoole-bundle/commit/b10bd6075f0bfce863be8fb7ea23f941d5e779aa))
* **tests:** fix for coverage tests - higher waiting values needed in some specific cases for coroutines tests ([066d5aa](https://github.com/symfony-swoole/swoole-bundle/commit/066d5aa84ed1224221abf6a3563052fecb85596d))
* **tests:** fixed coroutine feature tests with doctrine ([37f7417](https://github.com/symfony-swoole/swoole-bundle/commit/37f7417cac42ee59f8b886dbf9886375ee72467a))
* **tests:** fixed coroutine test with workers and sleep commands ([3d22d08](https://github.com/symfony-swoole/swoole-bundle/commit/3d22d0824659c8bf9c0d6f0d8f6b975dbc9964b9))
* **tests:** fox for exception handler tests + disabled container bodifications for non-coroutine workloads ([050c630](https://github.com/symfony-swoole/swoole-bundle/commit/050c630bab0442df45c042a41f4161d03c7fa7ce))
* **tests:** HMR reload test fix after it was broken 2 commits before this one ([9fd6e8c](https://github.com/symfony-swoole/swoole-bundle/commit/9fd6e8c71e34267b260fa7bd08a04687a740c7d1))
* **tests:** possible fix for coroutine tests with PCOV code coverage ([02376bc](https://github.com/symfony-swoole/swoole-bundle/commit/02376bc8799bf6016dc8409a55e431d10a773b00))
* **tests:** possible fix for coverage enabled tests (worked on CI directly) ([da9faad](https://github.com/symfony-swoole/swoole-bundle/commit/da9faade3fdd2bfbc73ff5e92423456f9c037ca6))
* **tests:** server tests need to clean up var folder on start and need to stop with same env variables ([46e2ae7](https://github.com/symfony-swoole/swoole-bundle/commit/46e2ae7d55a1124ae21bb075b88941491bd5530d))
* **tideways:** added Profiler::markAsWebTransaction() to the beginning of request profiling ([7d480f6](https://github.com/symfony-swoole/swoole-bundle/commit/7d480f6a5549e7a0f4bcf578661e05dd5102f389))


### Features

* **access-log:** added kernel event to write access logs to monolog handler, custom log format can be set ([b2d8b44](https://github.com/symfony-swoole/swoole-bundle/commit/b2d8b44b6d0ff44a2ddeec8db767b86816d2c90a))
* **blackfire:** added support for blackfire monitoring ([896a890](https://github.com/symfony-swoole/swoole-bundle/commit/896a890923c318dd4f68220a537a3ecba3d313b1))
* **command:** server shutdown, ignore graceful timeout ([299e444](https://github.com/symfony-swoole/swoole-bundle/commit/299e4442d7afe3372b8e75bd994fbdd5b046a8fc))
* **console:** added task worker count to the console table on server start ([c7b31ed](https://github.com/symfony-swoole/swoole-bundle/commit/c7b31ed3acabb533c59f4f72d64a93d88af6b011))
* **coroutine-limits:** unmanaged factory new instance limits are configurable per factory method now ([af50185](https://github.com/symfony-swoole/swoole-bundle/commit/af501858c66ca1f15dbb6c80dc2c43c579afe008))
* **coroutines:** ability to use coroutines in Symofny ([19eb714](https://github.com/symfony-swoole/swoole-bundle/commit/19eb71443b75f61a87568b5cba78ef6f1d646541))
* **coroutines:** added assign limit for proxified services using locking in proxies ([168e1f8](https://github.com/symfony-swoole/swoole-bundle/commit/168e1f8e6aaf3d15e7d4970dd074a3b9af8a5308))
* **coroutines:** added configurable doctrine connections limit per swoole process ([d5365d3](https://github.com/symfony-swoole/swoole-bundle/commit/d5365d3e0a0475fea6149bd6a646a70092b00988))
* **coroutines:** added configurable instance limits to stateful services and unmanaged factories using container tags ([ab4ff3b](https://github.com/symfony-swoole/swoole-bundle/commit/ab4ff3b642d49248a94073ac73ed7913a14feafa))
* **coroutines:** added support for coroutines into taks workers, configuration reordering and refactoring ([b5c4a09](https://github.com/symfony-swoole/swoole-bundle/commit/b5c4a09c39e273ddcea8797daed05a4ce0872e44))
* **coroutines:** added symfony cache adapter children class services proxification because SF cache AbstractAdapter is stateful ([b52b241](https://github.com/symfony-swoole/swoole-bundle/commit/b52b241da7b5b1c4fd104850ce150f64c9b8a7a7))
* **coroutines:** changed SF resetting mechanism so only needed services get reset in first usage ([7a568c7](https://github.com/symfony-swoole/swoole-bundle/commit/7a568c78163aef3f3455e16a8248b2be1a64cab6))
* **coroutines:** implemented max concurrency and max proxified instances limit ([8779aa3](https://github.com/symfony-swoole/swoole-bundle/commit/8779aa3c65d28a5ce8771b2254a4ea5cf34a318d))
* **coroutines:** safe stateful services need to be reset with the original sf resetter mechanism ([c7dab53](https://github.com/symfony-swoole/swoole-bundle/commit/c7dab53a16efebe0b56f47dd841b87ed9d943657))
* **events:** added swoole server events propagation for serverStart and workerStart ([a0b133f](https://github.com/symfony-swoole/swoole-bundle/commit/a0b133f84bd302139c0ce72e40efbfbeee77cca8))
* **hmr:** dump non-reloadable files included before server start - for local development (docker entrypoint) ([97bf19d](https://github.com/symfony-swoole/swoole-bundle/commit/97bf19da1a16b2a11b51e569fa7433df8d2098a2))
* **opcache:** added generation of opcache.blacklist_file when using coroutines, because of segfaults when using swoole with opcache ([0cade70](https://github.com/symfony-swoole/swoole-bundle/commit/0cade70d88101d232155f6ab2ad04adce9a5a4a1))
* **organisation:** pixelfederation organisation changed to openswoole-bundle (for packagist) and symfonywithswoole (for docker hub) ([b568ac0](https://github.com/symfony-swoole/swoole-bundle/commit/b568ac06c06ca9841c89d4744ff364bb94fc25a5))
* **profiling:** added support for blackfire to collect performance profiles from multiple requests combined into one profile (even with coroutines enabled) ([52f9133](https://github.com/symfony-swoole/swoole-bundle/commit/52f91337b5bdd4025d7558e675efa3a506fd5de3))
* **service-reset:** add possibility to mark some services to be reset on each request ([3661ff1](https://github.com/symfony-swoole/swoole-bundle/commit/3661ff13402f294964a148a663fd2da30aa65f46))
* **symfony:** added support for Symfony 6.3 ([38b09da](https://github.com/symfony-swoole/swoole-bundle/commit/38b09da39d02967551f98e1702be39c29f83e485))
* **testing:** tests are able to run also in prod env with container infline factories enabled, coroutines tests were changed accordingly ([cf8b635](https://github.com/symfony-swoole/swoole-bundle/commit/cf8b635c9735a95a22e5f6c0834fcccdd1a9fbbf))
* **worker:** added WorkerStop, WorkerError WorkerExit handlers ([6a1475b](https://github.com/symfony-swoole/swoole-bundle/commit/6a1475b0a7e2b8c0d925ee37df9ce053f84d6ac8))

## [0.13.1](https://github.com/pixelfederation/swoole-bundle/compare/v0.13.0...v0.13.1) (2022-08-19)

[Full changelog](https://github.com/pixelfederation/swoole-bundle/compare/v0.13.0...v0.13.1)

### Bug Fixes

* **ci:** added github repository to circle ci as parameter to be able to use unified releaser script ([71a5280](https://github.com/pixelfederation/swoole-bundle/commit/71a528096ed9452f588e54a931c951c5172a274c))
* **dependabot:** target branch changed to develop ([920efa4](https://github.com/pixelfederation/swoole-bundle/commit/920efa41fe3253b65fcca294a4527569339af335))

# [0.13.0](https://github.com/pixelfederation/swoole-bundle/compare/v0.12.0...v0.13.0) (2022-08-16)

[Full changelog](https://github.com/pixelfederation/swoole-bundle/compare/v0.12.0...v0.13.0)

### Features

* **profiling:** added Tideways support ([bfc56ad](https://github.com/pixelfederation/swoole-bundle/commit/bfc56ad20c01c21303c4b54fe1c39235178a6c37))

# [0.12.0](https://github.com/pixelfederation/swoole-bundle/compare/v0.11.1...v0.12.0) (2022-07-27)

[Full changelog](https://github.com/pixelfederation/swoole-bundle/compare/v0.11.1...v0.12.0)

### Bug Fixes

* **readme:** fixed the composer install reference ([12a24d6](https://github.com/pixelfederation/swoole-bundle/commit/12a24d6a0a326d86ba14a6c42268a229bf58eed4))


### Features

* **docker-compose:** added releaser bot ([9cf4876](https://github.com/pixelfederation/swoole-bundle/commit/9cf4876147c7d125825e66061c5f63d62f099df3))
* **platform:** added integrations for PHP 8.1 + added support for Symfony 6 ([368f99e](https://github.com/pixelfederation/swoole-bundle/commit/368f99e002c3b6c7de1f43d85ac7f4e043845565))

## [0.11.1](https://github.com/pixelfederation/swoole-bundle/compare/v0.11.0...v0.11.1) (2022-03-20)

[Full changelog](https://github.com/pixelfederation/swoole-bundle/compare/v0.11.0...v0.11.1)

### Miscellaneous

* Minor fixes

# [0.11.0](https://github.com/pixelfederation/swoole-bundle/compare/v0.10.0...v0.11.0) (2022-03-20)

[Full changelog](https://github.com/pixelfederation/swoole-bundle/compare/v0.10.0...v0.11.0)

### Features

* **http-kernel:** remove debug http request handler ([#516](https://github.com/pixelfederation/swoole-bundle/issues/516)) ([da33c81](https://github.com/pixelfederation/swoole-bundle/commit/da33c81116a2d3033785985d65c592e2eb071f40))
* **openswoole:** openswoole support (4.10.0) instead of swoole ([ac7ab0a](https://github.com/pixelfederation/swoole-bundle/commit/ac7ab0a252e3bd8bf10b5b85f3833e707e74360a))

# [0.10.0](https://github.com/k911/swoole-bundle/compare/v0.9.0...v0.10.0) (2021-05-05)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.9.0...v0.10.0)

### Features

* **response-processor:** hiding Swoole StreamedResponse support from Symfony ([#509](https://github.com/k911/swoole-bundle/issues/509)) ([ebe8077](https://github.com/k911/swoole-bundle/commit/ebe80770e9132b45f6a74ad233f8b8b707854f2e))

# [0.9.0](https://github.com/k911/swoole-bundle/compare/v0.8.3...v0.9.0) (2021-02-03)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.8.3...v0.9.0)

### Bug Fixes

* **request-factory:** Avoid accessing undefined index REQUEST_URI ([#422](https://github.com/k911/swoole-bundle/issues/422)) ([807ba9f](https://github.com/k911/swoole-bundle/commit/807ba9f0c7ceaa523af76899ce154c816ab69242))


### Features

* **response-processor:** add support for StreamedResponse ([89fc7ca](https://github.com/k911/swoole-bundle/commit/89fc7cac9864465cc707224422e81ad0d101edc4))

## [0.8.3](https://github.com/k911/swoole-bundle/compare/v0.8.2...v0.8.3) (2021-01-03)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.8.2...v0.8.3)

### Bug Fixes

* **session-storage:** Reset session storage on kernel.finish_request ([6b7a992](https://github.com/k911/swoole-bundle/commit/6b7a9923bece217e1cfa43b7fb6cd0016d8069af))
* allow defining no log file path to enable logging to stdout ([#301](https://github.com/k911/swoole-bundle/issues/301)) ([eea4a4f](https://github.com/k911/swoole-bundle/commit/eea4a4f41e6e8100affb763b18fa62403d30e705))

## [0.8.2](https://github.com/k911/swoole-bundle/compare/v0.8.1...v0.8.2) (2020-07-20)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.8.1...v0.8.2)

### Miscellaneous

* Minor fixes

## [0.8.1](https://github.com/k911/swoole-bundle/compare/v0.8.0...v0.8.1) (2020-07-14)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.8.0...v0.8.1)

### Bug Fixes

* **doctrine:** autoconfigure EntityManagerHandler only when orm is available in symfony's container ([#274](https://github.com/k911/swoole-bundle/issues/274)) ([87ede15](https://github.com/k911/swoole-bundle/commit/87ede156bc25f3b774a9913cff9db3d78d39b129))
* **http:** proper creation of $_SERVER['REQUEST_URI'] ([#269](https://github.com/k911/swoole-bundle/issues/269)) ([78bb42b](https://github.com/k911/swoole-bundle/commit/78bb42b559faae94f7eeac3aebb8886992714214))

# [0.8.0](https://github.com/k911/swoole-bundle/compare/v0.7.9...v0.8.0) (2020-06-23)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.9...v0.8.0)

### Bug Fixes

* **config:** allow configuring worker and reactor counts using ENV variables ([#244](https://github.com/k911/swoole-bundle/issues/244)) ([d6b270a](https://github.com/k911/swoole-bundle/commit/d6b270a3d3f2895d6cef55b3272f57ae30d48657))
* **profiler:** make log collection in symfony profiler to work ([#242](https://github.com/k911/swoole-bundle/issues/242)) ([50fdd6f](https://github.com/k911/swoole-bundle/commit/50fdd6fa0ab98b83c30a456405e203f6296cf2fd))


### Features

* **blackfire:** Add bridge for upscale/swoole-blackfire ([#221](https://github.com/k911/swoole-bundle/issues/221)) ([960ddb8](https://github.com/k911/swoole-bundle/commit/960ddb84004bf3b146cdddd81cc60a48c60efa0b))
* **exception-handler:** Add Symfony error/exception handler ([#228](https://github.com/k911/swoole-bundle/issues/228)) ([180d5e5](https://github.com/k911/swoole-bundle/commit/180d5e5a11b67097c66a0a2fecaf292bfa14cc4c))
* **http-server:** configurable mime types for advanced static files server ([#240](https://github.com/k911/swoole-bundle/issues/240)) ([07896a4](https://github.com/k911/swoole-bundle/commit/07896a45dccf22320cb26e0988fa4988d18cd782))

## [0.7.9](https://github.com/k911/swoole-bundle/compare/v0.7.8...v0.7.9) (2020-05-20)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.8...v0.7.9)

### Bug Fixes

* **server:** add worker_max_request and worker_max_request_grace configuration options ([#220](https://github.com/k911/swoole-bundle/issues/220)) ([69fd435](https://github.com/k911/swoole-bundle/commit/69fd435717833d224ee6087c46fd96155e362d02))

## [0.7.8](https://github.com/k911/swoole-bundle/compare/v0.7.7...v0.7.8) (2020-05-03)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.7...v0.7.8)

### Miscellaneous

* Minor fixes

## [0.7.7](https://github.com/k911/swoole-bundle/compare/v0.7.6...v0.7.7) (2020-05-01)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.6...v0.7.7)

### Miscellaneous

* Minor fixes

## [0.7.6](https://github.com/k911/swoole-bundle/compare/v0.7.5...v0.7.6) (2020-04-01)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.5...v0.7.6)

### Bug Fixes

* **http-server:** return all headers with the same name joined by comma ([#175](https://github.com/k911/swoole-bundle/issues/175)) ([1e51639](https://github.com/k911/swoole-bundle/commit/1e51639306bb8f8b4d4f636c1ef1db80a6ab9b7f))

## [0.7.5](https://github.com/k911/swoole-bundle/compare/v0.7.4...v0.7.5) (2019-12-19)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.4...v0.7.5)

### Bug Fixes

* **server:** Add missing setting for "package_max_length" ([#96](https://github.com/k911/swoole-bundle/issues/96)) ([37758f2](https://github.com/k911/swoole-bundle/commit/37758f280e853d9c40c5dfe124ff3036e66d5294))

## [0.7.4](https://github.com/k911/swoole-bundle/compare/v0.7.3...v0.7.4) (2019-12-03)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.3...v0.7.4)

### Bug Fixes

* **server:** Use `SplFile::getRealPath` for `Response::sendfile` Operation for a BinaryFileReponse ([#91](https://github.com/k911/swoole-bundle/issues/91)) ([0278db7](https://github.com/k911/swoole-bundle/commit/0278db725c6b5f52bb513454a59ca16bef67f1da)), closes [#90](https://github.com/k911/swoole-bundle/issues/90)

## [0.7.3](https://github.com/k911/swoole-bundle/compare/v0.7.2...v0.7.3) (2019-11-30)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.2...v0.7.3)

### Reverts

* Revert "ci(circle): Remove loop in bash release script" ([a468ef7](https://github.com/k911/swoole-bundle/commit/a468ef7b455aa097ea573aeb35681fc19d753a4c)), closes [#81](https://github.com/k911/swoole-bundle/issues/81)

## [0.7.2](https://github.com/k911/swoole-bundle/compare/v0.7.1...v0.7.2) (2019-11-30)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.1...v0.7.2)

### Bug Fixes

* **composer:** Resolve upgrade issues ([#83](https://github.com/k911/swoole-bundle/issues/83)) ([92285ac](https://github.com/k911/swoole-bundle/commit/92285ac63b62579f5e23e965e5aa020f7e407c02))

## [0.7.1](https://github.com/k911/swoole-bundle/compare/v0.7.0...v0.7.1) (2019-11-14)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.7.0...v0.7.1)

### Miscellaneous

* Minor fixes

# [0.7.0](https://github.com/k911/swoole-bundle/compare/v0.6.2...v0.7.0) (2019-11-13)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.6.2...v0.7.0)

### Bug Fixes

* **http-client:** Make HttpClient serializable ([0ee8918](https://github.com/k911/swoole-bundle/commit/0ee89182073edfa46cefaea2d58731130d3a7252))
* **http-server:** Add top-level exception handler to prevent server timeouts ([#79](https://github.com/k911/swoole-bundle/issues/79)) ([08c76c4](https://github.com/k911/swoole-bundle/commit/08c76c4a0fa73c2b846d48f1a8afc0e8aef0b265)), closes [#78](https://github.com/k911/swoole-bundle/issues/78)


### Features

* **session:** Add in-memory syfmony session storage ([#73](https://github.com/k911/swoole-bundle/issues/73)) ([4ccdca0](https://github.com/k911/swoole-bundle/commit/4ccdca0f4709d0ab83bba068b6fad0376d53b849))

## [0.6.2](https://github.com/k911/swoole-bundle/compare/v0.6.1...v0.6.2) (2019-10-05)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.6.1...v0.6.2)

### Miscellaneous

* Minor fixes

## [0.6.1](https://github.com/k911/swoole-bundle/compare/v0.6.0...v0.6.1) (2019-10-04)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.6.0...v0.6.1)

### Miscellaneous

* Minor fixes


# [0.6.0](https://github.com/k911/swoole-bundle/compare/v0.5.3...v0.6.0) (2019-08-11)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.5.3...v0.6.0)

### Features

* **messenger:** Add Symfony Messenger integration ([#56](https://github.com/k911/swoole-bundle/issues/56)) ([d136313](https://github.com/k911/swoole-bundle/commit/d136313)), closes [#4](https://github.com/k911/swoole-bundle/issues/4)


## [0.5.3](https://github.com/k911/swoole-bundle/compare/v0.5.2...v0.5.3) (2019-06-06)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.5.2...v0.5.3)

### Bug Fixes

* **config:** set default host value to '0.0.0.0' ([#55](https://github.com/k911/swoole-bundle/issues/55)) ([2c9221d](https://github.com/k911/swoole-bundle/commit/2c9221d))


## [0.5.2](https://github.com/k911/swoole-bundle/compare/v0.5.1...v0.5.2) (2019-04-30)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.5.1...v0.5.2)

### Bug Fixes

* **server:** Make sure "reactor" running mode works correctly ([#53](https://github.com/k911/swoole-bundle/issues/53)) ([69dfea2](https://github.com/k911/swoole-bundle/commit/69dfea2))


## [0.5.1](https://github.com/k911/swoole-bundle/compare/v0.5.0...v0.5.1) (2019-04-28)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.5.0...v0.5.1)

### Bug Fixes

* **static-server:** Fix unset public dir path in "AdvancedStaticFilesServer" ([#52](https://github.com/k911/swoole-bundle/issues/52)) ([4ef8cb5](https://github.com/k911/swoole-bundle/commit/4ef8cb5))


# [0.5.0](https://github.com/k911/swoole-bundle/compare/v0.4.4...v0.5.0) (2019-04-26)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.4.4...v0.5.0)

### Bug Fixes

* **di:** Do not use integer node for port ([ac6fdcf](https://github.com/k911/swoole-bundle/commit/ac6fdcf))
* **hmr:** Drop unused reference to SymfonyStyle object in InotifyHMR ([6b22485](https://github.com/k911/swoole-bundle/commit/6b22485))
* **reload:** Make sure command works on macOS system ([4d99e9c](https://github.com/k911/swoole-bundle/commit/4d99e9c))

### Features

* **apiserver:** Create API Server component ([#32](https://github.com/k911/swoole-bundle/issues/32)) ([a8d0ec2](https://github.com/k911/swoole-bundle/commit/a8d0ec2)), closes [#2](https://github.com/k911/swoole-bundle/issues/2)
* **server:** Add setting for "buffer_output_size" ([#33](https://github.com/k911/swoole-bundle/issues/33)) ([7a50864](https://github.com/k911/swoole-bundle/commit/7a50864))
* **server:** Set-up hooks on lifecycle events ([271a341](https://github.com/k911/swoole-bundle/commit/271a341))
* Add meaningful exceptions ([#46](https://github.com/k911/swoole-bundle/issues/46)) ([4e2cc6d](https://github.com/k911/swoole-bundle/commit/4e2cc6d))

## [0.4.4](https://github.com/k911/swoole-bundle/compare/v0.4.3...v0.4.4) (2019-01-06)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.4.3...v0.4.4)

### Bug Fixes

* **di:** Fix regression introduced in v0.4.3 ([#29](https://github.com/k911/swoole-bundle/issues/29)) ([c88fcf2](https://github.com/k911/swoole-bundle/commit/c88fcf2))

## [0.4.3](https://github.com/k911/swoole-bundle/compare/v0.4.2...v0.4.3) (2019-01-06)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.4.2...v0.4.3)

### Bug Fixes

* **di:** Fix detection of doctrine bundle ([ef5920c](https://github.com/k911/swoole-bundle/commit/ef5920c))

## [0.4.2](https://github.com/k911/swoole-bundle/compare/v0.4.1...v0.4.2) (2018-11-05)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.4.1...v0.4.2)

### Bug Fixes

* **xdebug-handler:** Remove process timeout ([#23](https://github.com/k911/swoole-bundle/issues/23)) ([29148af](https://github.com/k911/swoole-bundle/commit/29148af))


## [0.4.1](https://github.com/k911/swoole-bundle/compare/v0.4.0...v0.4.1) (2018-10-24)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.4.0...v0.4.1)

### Bug Fixes

* **boot-manager:** Don't boot not bootable objects ([8ad97a2](https://github.com/k911/swoole-bundle/commit/8ad97a2)), closes [#19](https://github.com/k911/swoole-bundle/issues/19)
* **xdebug-handler:** Replace with custom solution ([0dc13f0](https://github.com/k911/swoole-bundle/commit/0dc13f0)), closes [#13](https://github.com/k911/swoole-bundle/issues/13)

# [0.4.0](https://github.com/k911/swoole-bundle/compare/v0.3.0...v0.4.0) (2018-10-20)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.3.0...v0.4.0)

### Bug Fixes

* **command:** Graceful shutdown ([7e6c9a4](https://github.com/k911/swoole-bundle/commit/7e6c9a4))


### Code Refactoring

* **di:** Simplify registering configurators ([#14](https://github.com/k911/swoole-bundle/issues/14)) ([a34d59c](https://github.com/k911/swoole-bundle/commit/a34d59c))


### Features

* **hmr:** Implement HMR with Inotify ([97e88bb](https://github.com/k911/swoole-bundle/commit/97e88bb))


### BREAKING CHANGES

- `Server\HttpServerFactory` should not be instantiated anymore, due to
removed hard coupling with `Server\Configurator\ConfiguratorInterface`, and `make()` method
becomig static. Now use directly: `Server\HttpServerFactory::make()`
- Configuring server (using object implementing `Server\Configurator\ConfiguratorInterface`)
now happens in execute method of AbstractServerStartCommand
- `Server\Configurator\ChainConfigurator` is now replaced by `Server\Configurator\GeneratedChainConfigurator`


# [0.3.0](https://github.com/k911/swoole-bundle/compare/v0.2.0...v0.3.0) (2018-10-13)

[Full changelog](https://github.com/k911/swoole-bundle/compare/v0.2.0...v0.3.0)

### Bug Fixes

* **io:** Properly close stdout/stderr ([94041e6](https://github.com/k911/swoole-bundle/commit/94041e6))


### Features

* **daemon-mode:** Daemonize Swoole HTTP server ([#8](https://github.com/k911/swoole-bundle/issues/8)) ([3cca5c4](https://github.com/k911/swoole-bundle/commit/3cca5c4))



# [0.2.0](https://github.com/k911/swoole-bundle/compare/c5a0c27...v0.2.0) (2018-10-07)

[Full changelog](https://github.com/k911/swoole-bundle/compare/c5a0c27...v0.2.0)

### Bug Fixes

* **command:** Decode configuration one more time ([32f9776](https://github.com/k911/swoole-bundle/commit/32f9776))
* **config:** Add trusted_proxies and trusted_hosts ([aae8873](https://github.com/k911/swoole-bundle/commit/aae8873)), closes [#5](https://github.com/k911/swoole-bundle/issues/5)
* **configuration:** Set proper service ids in symfony DI ([dda8c9d](https://github.com/k911/swoole-bundle/commit/dda8c9d))


### Features

* **swoole:** Allow to change publicdir at runtime ([c5a0c27](https://github.com/k911/swoole-bundle/commit/c5a0c27))


### BREAKING CHANGES

* Env `APP_TRUSTED_HOSTS` is no longer supported
* Env `APP_TRUSTED_PROXIES` is no longer supported
* Configuration `swoole.http_server.services.debug` is renamed to `swoole.http_server.services.debug_handler`
* Configuration `swoole.http_server.services.trust_all_proxies` is renamed to `swoole.http_server.services.trust_all_proxies_handler`
