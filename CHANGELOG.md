# Changelog

## [2.0.0](https://github.com/rtCamp/onedesign/compare/v1.1.3...v2.0.0) (2026-10-31)


### ⚠ BREAKING CHANGES

* **library:** apply templates from a plain Template Library screen ([#190](https://github.com/rtCamp/onedesign/issues/190))
* **library:** share patterns from a plain Pattern Library screen ([#189](https://github.com/rtCamp/onedesign/issues/189))
* refuse brand pattern requests with an empty key or from the wrong site ([#187](https://github.com/rtCamp/onedesign/issues/187))
* bump min WP to 6.9, and fix deps and release versioning ([#183](https://github.com/rtCamp/onedesign/issues/183))
* backport repo changes ([#147](https://github.com/rtCamp/onedesign/issues/147))
* refactor for best practices ([#30](https://github.com/rtCamp/onedesign/issues/30))

### dev

* refactor for best practices ([#30](https://github.com/rtCamp/onedesign/issues/30)) ([f74fc4e](https://github.com/rtCamp/onedesign/commit/f74fc4e4a90ab2ef971fb3aa01045cc3869dd2c3))


### Added

* add custom category support ([4c41ae9](https://github.com/rtCamp/onedesign/commit/4c41ae949df3f2ee532c2ec6b4955e6af0446955))
* add custom category support ([9b7e1a8](https://github.com/rtCamp/onedesign/commit/9b7e1a86a4a9a0d919acdc38fcf30eb3c3b7ffd0))
* add phpcbf pre-commit hook and fix failing CI ([#145](https://github.com/rtCamp/onedesign/issues/145)) ([43eea91](https://github.com/rtCamp/onedesign/commit/43eea913c485b795398ed8591ed186e098e9841b))
* add Playwright E2E testing scaffold ([#144](https://github.com/rtCamp/onedesign/issues/144)) ([c65646c](https://github.com/rtCamp/onedesign/commit/c65646cc174a82e2c4710e563bb4de518627e2bc))
* added admin page logo ([29c42f6](https://github.com/rtCamp/onedesign/commit/29c42f6840a742acf2eae4fe105859fae0e4df48))
* added cleanup on plugin uninstallation ([3ba0323](https://github.com/rtCamp/onedesign/commit/3ba0323364613c2b62112e4c7d1fa3392f6febc4))
* added required translation file ([ad2b13d](https://github.com/rtCamp/onedesign/commit/ad2b13d713c51558d1d5f9bce15ef7ae7c93f571))
* added site mode selection modal on plugin activation ([3e8672b](https://github.com/rtCamp/onedesign/commit/3e8672bd722f4383d6ea9a42203481ac3d2f5f2f))
* added synced pattern to template sharing ([38a3c6f](https://github.com/rtCamp/onedesign/commit/38a3c6fb6f7a14cc29df365e395ba114d2a1bdb9))
* added warning if site is not reachable ([3518cc2](https://github.com/rtCamp/onedesign/commit/3518cc254993b00f6c5662bdb8da896e9f21352e))
* auto api key updation and modal markup for governing site selection ([fbdbe71](https://github.com/rtCamp/onedesign/commit/fbdbe71c30c048368657a177d7d60c99715872f2))
* auto assign brand-site to new created sites in same MU ([4922ca8](https://github.com/rtCamp/onedesign/commit/4922ca80f1639f2b167d7e8a8a6e95a5da2a4865))
* change document title same as modal ([e60562f](https://github.com/rtCamp/onedesign/commit/e60562fc3c8008e97cc7f2affb63e81d57cda815))
* converted PHP settings to gutenberg components ([5febb73](https://github.com/rtCamp/onedesign/commit/5febb73a4c3b395bfdbcb5e917b248f0f17bb388))
* created modal for adding multisite brand sites ([e29b830](https://github.com/rtCamp/onedesign/commit/e29b830d9f3e4703355b39601fea3717e10f98f9))
* created modal for plugins network activation to select governing site ([94c38bb](https://github.com/rtCamp/onedesign/commit/94c38bb2f5e71cdd6771da64f3bd016dbb41e5a2))
* created re-usable hook for sites health check info ([6f279a4](https://github.com/rtCamp/onedesign/commit/6f279a400fa6bd213deb51c2cf3b230ec0e8df3c))
* created required helpers utils functions ([03fd03f](https://github.com/rtCamp/onedesign/commit/03fd03f723912d04fbab420f76e501ad55f4957f))
* created required rest routes for multisite configurations ([0131813](https://github.com/rtCamp/onedesign/commit/0131813a74a9739ffad4f574cae3c91710cbe84a))
* created required routes for gutenberg settings page ([7c09f16](https://github.com/rtCamp/onedesign/commit/7c09f1644c68582e0a9663f9089d170d7a1225b2))
* created separate class for handling api key ([5627c95](https://github.com/rtCamp/onedesign/commit/5627c957f443129cc3fa2926640327cdd828bacd))
* remove all options/posts on plugin network deletion ([1b06592](https://github.com/rtCamp/onedesign/commit/1b065928ae40b95c367fc13c1d2aedf2f060f720))
* sync shared templates ([ad01dec](https://github.com/rtCamp/onedesign/commit/ad01dec3187d97f1093956e60e6f81aea0679328))


### Fixed

* absolute path issue ([c47879d](https://github.com/rtCamp/onedesign/commit/c47879d4b7cd8654188c8e866b5af80c945f8c19))
* align stylelint config and apply ([#180](https://github.com/rtCamp/onedesign/issues/180)) ([e856e60](https://github.com/rtCamp/onedesign/commit/e856e60fe0e71c4bee95d83b9a0652fad58985eb))
* bump min WP to 6.9, and fix deps and release versioning ([#183](https://github.com/rtCamp/onedesign/issues/183)) ([57b177b](https://github.com/rtCamp/onedesign/commit/57b177b80a7f5099d937a56e502a19061c7e89c4))
* caching issues ([0ef6e48](https://github.com/rtCamp/onedesign/commit/0ef6e48c56f21620c8cdcf3ad10ebee3fa9d3f0f))
* change settings page position ([5da6d77](https://github.com/rtCamp/onedesign/commit/5da6d77d9ee7f42975359788f652fbe3f9213c0d))
* change settings page position ([7880bfb](https://github.com/rtCamp/onedesign/commit/7880bfbbd5a4f7ca8cb2c2add6d85a6c6098b392))
* cleanup unused assets ([#182](https://github.com/rtCamp/onedesign/issues/182)) ([1a8330a](https://github.com/rtCamp/onedesign/commit/1a8330ae59db5c61f7ab4a2fa8245205847a2f4d))
* code issues & unify utils and constants usage ([baf93a4](https://github.com/rtCamp/onedesign/commit/baf93a49236a4097aa7ee8381537a7be630d8f5c))
* coerce `Encryptor::decrypt()` to string ([#76](https://github.com/rtCamp/onedesign/issues/76)) ([72449fe](https://github.com/rtCamp/onedesign/commit/72449fe7eea3dbcbfd6f1eb744d6baddca79ef56))
* copilot review ([e3ec439](https://github.com/rtCamp/onedesign/commit/e3ec4399fe6b25e5bcf31617aee8172acf882f76))
* copilot review ([a851884](https://github.com/rtCamp/onedesign/commit/a851884d037fee203bd7f18fe35e284c2754593c))
* deprecated warnings & pattern checkbox events issue ([#78](https://github.com/rtCamp/onedesign/issues/78)) ([f9ea06c](https://github.com/rtCamp/onedesign/commit/f9ea06c583e39e72b6c2f7550cded64e35077a71))
* drop dead PHP code and keep a script's dependencies when registered twice ([#186](https://github.com/rtCamp/onedesign/issues/186)) ([f8627c3](https://github.com/rtCamp/onedesign/commit/f8627c3e60427a8f5b72e46b09581e222896e6cb))
* formatting issue ([d72fcbd](https://github.com/rtCamp/onedesign/commit/d72fcbdd0865bc9b9d259b19ed7aaf182be82e02))
* **i18n:** load translations for the plugin's scripts ([#184](https://github.com/rtCamp/onedesign/issues/184)) ([af1d370](https://github.com/rtCamp/onedesign/commit/af1d370d648b69050ce4f5eba9fdb3c08884e715))
* **library:** apply templates from a plain Template Library screen ([#190](https://github.com/rtCamp/onedesign/issues/190)) ([b7b8703](https://github.com/rtCamp/onedesign/commit/b7b8703738715f309da8a8301ca220923503d9ad))
* **library:** keep received patterns' namespaced names and strip their scripts ([#188](https://github.com/rtCamp/onedesign/issues/188)) ([615cf79](https://github.com/rtCamp/onedesign/commit/615cf7939645f5ec5b1c9195c36f15737244028d))
* **library:** share patterns from a plain Pattern Library screen ([#189](https://github.com/rtCamp/onedesign/issues/189)) ([46b36f6](https://github.com/rtCamp/onedesign/commit/46b36f630fd0e069875a714bbc7229a69db910e8))
* linting & phpcs issues ([8a0d220](https://github.com/rtCamp/onedesign/commit/8a0d2206f59df7961db5a2d44d63390659de58e0))
* linting issue ([1763e10](https://github.com/rtCamp/onedesign/commit/1763e106430b3abf5cf87030a86794751b706efb))
* package inconsistency ([42dd0e8](https://github.com/rtCamp/onedesign/commit/42dd0e8847e9f2b66747767384870c3b358b708b))
* package name ([77ce743](https://github.com/rtCamp/onedesign/commit/77ce743610280a49021a0f3fcd858eed3e2e196e))
* patterns site tabs issue ([28e84f2](https://github.com/rtCamp/onedesign/commit/28e84f2df9974c3505ae78d1c1fa9faa8a65375a))
* PHPCS fixes ([17ce57e](https://github.com/rtCamp/onedesign/commit/17ce57e881ccf157b1d6bb4b096aaaf3d743a87a))
* PHPCS issue ([30cd72f](https://github.com/rtCamp/onedesign/commit/30cd72f8ddb2ec6910332aa64341ac429c7d319f))
* phpcs issues ([a6d3066](https://github.com/rtCamp/onedesign/commit/a6d3066afaf459f4052f5b3032c9c0bf932586c3))
* plugin checker issue of release workflow ([975bc14](https://github.com/rtCamp/onedesign/commit/975bc14ce6d3045fc61aa08de736d1b471540c4b))
* QA issue after code refactoring ([#31](https://github.com/rtCamp/onedesign/issues/31)) ([66ac2de](https://github.com/rtCamp/onedesign/commit/66ac2def06690cd147c8e15039220501ad0a51aa))
* QA issues ([5520b15](https://github.com/rtCamp/onedesign/commit/5520b15d57acded4926dcb45be53c13659ee0626))
* refuse brand pattern requests with an empty key or from the wrong site ([#187](https://github.com/rtCamp/onedesign/issues/187)) ([63100dc](https://github.com/rtCamp/onedesign/commit/63100dc4984d0a39e4bfd056d9d9f65f69ceff29))
* release workflow ([477d61f](https://github.com/rtCamp/onedesign/commit/477d61f653bdcf3f1f467fa092ed44c201271df0))
* settings cleanup ([#185](https://github.com/rtCamp/onedesign/issues/185)) ([a2ae600](https://github.com/rtCamp/onedesign/commit/a2ae600c316e883d16ea33bdbbe0c303c046934c))
* show more issue of template sharing ([#32](https://github.com/rtCamp/onedesign/issues/32)) ([0bcbac0](https://github.com/rtCamp/onedesign/commit/0bcbac04e84013f78ddd02da5bec7fbc487d8364))
* site type issue in template sharing ([7d56343](https://github.com/rtCamp/onedesign/commit/7d563435bdc8f0edb663014fb57f9272979a1fbe))
* site_id type issue ([65b886e](https://github.com/rtCamp/onedesign/commit/65b886e504c3797b614e0bd3368673a19db5a46a))
* templates post type issue ([e0645f5](https://github.com/rtCamp/onedesign/commit/e0645f5855a75ab4d3839496ca5ebe89ffb6e033))
* theme issue of template part ([d5a619a](https://github.com/rtCamp/onedesign/commit/d5a619aa5d09df97ed5e2ccfc21b22238c28d943))
* type mis-matching issue with id ([ab37226](https://github.com/rtCamp/onedesign/commit/ab3722616b873f9e7deaa7f3ab76f8168567bcd0))
* typo ([6ca4baa](https://github.com/rtCamp/onedesign/commit/6ca4baac377b6c89816a34e3be8e7747f137c5c3))


### Changed

* **deps-dev:** bump launch-editor from 2.14.1 to 2.14.2 ([#176](https://github.com/rtCamp/onedesign/issues/176)) ([78d650d](https://github.com/rtCamp/onedesign/commit/78d650d6e3bd743d95e3a320c8dfb39d990099dd))
* **deps-dev:** bump the composer-dev-minor-patch group with 2 updates ([#163](https://github.com/rtCamp/onedesign/issues/163)) ([422c860](https://github.com/rtCamp/onedesign/commit/422c860dce287b95b47de97ffc8ec3a8f58ff741))
* **deps-dev:** bump wp-phpunit/wp-phpunit ([#197](https://github.com/rtCamp/onedesign/issues/197)) ([cf0602c](https://github.com/rtCamp/onedesign/commit/cf0602cc302887fd9102d90a2795f521c812fa44))
* **deps:** bump dompurify ([#173](https://github.com/rtCamp/onedesign/issues/173)) ([1981b3a](https://github.com/rtCamp/onedesign/commit/1981b3a05c1ffd212d4db7fa40cf6a0f8fe62cfd))
* **deps:** bump simple-git and @wordpress/env ([#177](https://github.com/rtCamp/onedesign/issues/177)) ([c062ae8](https://github.com/rtCamp/onedesign/commit/c062ae8bb4c4231512a847ed04c924c61350057f))
* **deps:** bump the github-actions-updates group with 2 updates ([#164](https://github.com/rtCamp/onedesign/issues/164)) ([fafdd93](https://github.com/rtCamp/onedesign/commit/fafdd93674b00b0de8fd87b875745d152fd14fff))
* **deps:** bump ws and @wp-playground/cli ([#148](https://github.com/rtCamp/onedesign/issues/148)) ([250422e](https://github.com/rtCamp/onedesign/commit/250422ee6eb43b6f205fce0a7065961527039be0))


### Miscellaneous Chores

* backport repo changes ([#147](https://github.com/rtCamp/onedesign/issues/147)) ([7ddbadc](https://github.com/rtCamp/onedesign/commit/7ddbadc80904ba55b65cc0e9f3c98a143dfe628a))

## 1.1.3

- Fix: Update NPM dependencies to resolve vulnerabilities

## 1.1.2

- Security: Resolve vulnerabilities in transitive NPM dependencies

## 1.1.1

- Feat: update Composer and NPM dependencies

## 1.1.0

- Feat: Refactor for WPCS and best practices

## 1.0.0-beta

- Initial public release
