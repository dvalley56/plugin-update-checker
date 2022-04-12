<?php


/**
 * Read this before making any comment to me
 * I am not familier with php or wordpress
 * I had to made this for our project
 * I did my best to make it work
 * 
 * This code here targets the version which is specified in our main plugin file in our repo in our main branch,  
 * and compares the version with the one in our local site. I have no idea how it does but it works
 * 
 * All the field in the url with {} has to be replaced with the actual value
 * I have tried to provide referal url for each url i have used
 * {organization} is the name of the organization
 * {project} is the name of the project
 * {repositoryId} is the name of the repositoryId, it would be something like uuid
 * {definationId} is the name of the definationId, it would be an +ve integer
 * 
 */
if ( !class_exists('Puc_v4p11_Vcs_AzureDevOpsApi', false) ):

	class Puc_v4p11_Vcs_AzureDevOpsApi extends Puc_v4p11_Vcs_Api {
		/**
		 * @var string GitHub username.
		 */
		protected $userName;
		/**
		 *
		 *
		 * @var string GitHub repository name.
		 */
		protected $repositoryName;

		/**
		 * @var string Either a fully qualified repository URL, or just "user/repo-name".
		 */
		protected $repositoryUrl;

		/**
		 * @var string GitHub authentication token. Optional.
		 */
		protected $accessToken;

		/**
		 * @var bool Whether to download release assets instead of the auto-generated source code archives.
		 */
		protected $releaseAssetsEnabled = false;

		/**
		 * @var string|null Regular expression that's used to filter release assets by name. Optional.
		 */
		protected $assetFilterRegex = null;

		/**
		 * @var string|null The unchanging part of a release asset URL. Used to identify download attempts.
		 */
		protected $assetApiBaseUrl = null;

		/**
		 * @var bool
		 */
		private $downloadFilterAdded = false;

		public function __construct($repositoryUrl, $accessToken = null) {
				$this->userName = ''; // Your User Name eg. John Doe
				$this->repositoryName = ''; // Your Azure Repository name eg. MyPlugin
			parent::__construct($repositoryUrl, $accessToken);
		}

		/**
		 * Get the latest release from GitHub.
		 *
		 * @return Puc_v4p11_Vcs_Reference|null
		 */

		public function getLatestRelease() {
			/*
				   https://docs.microsoft.com/en-us/rest/api/azure/devops/release/definitions/get?view=azure-devops-rest-6.0
					Refer this url for more details about the url to use below
					organization: Your Organization name
					project: Your Project name 
					definitionId: The ID of the release definition, it would be an integer
			*/
			$release = $this->api('https://vsrm.dev.azure.com/{organiztion}/{project}/_apis/release/releases?definitionid={definitionId}&api-version=6.0');
			//The above url returns an array of releases so we have to get the latest release, that is why index 0.
			$release = $release->value[0];

			if ( is_wp_error($release) || !is_object($release) || !isset($release->name) ) {
				return null;
			}


			$reference = new Puc_v4p11_Vcs_Reference(array(
				'name'        => $release->name,
				'version'     => ltrim($release->name, 'Release-'), //Remove the "Release-" prefix, Eg : "Release-2".
				'downloadUrl' => 'https://dev.azure.com/{organiztion}/{project}/_apis/git/repositories/{repositoryname}/items/items?path=/&$format=zip&download=true',
				'updated'     => $release->createdOn,
				'apiResponse' => $release,
			));
			return $reference;
		}

		/**
		 * Get the tag that looks like the highest version number.
		 *
		 * @return Puc_v4p11_Vcs_Reference|null
		 */
		public function getLatestTag() {
			$release = $this->api('https://vsrm.dev.azure.com/{organiztion}/{project}/_apis/release/releases?definitionid={definitionId}&api-version=6.0');
			$release = $release->value[0];

			return new Puc_v4p11_Vcs_Reference(array(
				'name'        => $release->name,
				'version'     => ltrim($release->name, 'Release-'), //Remove the "v" prefix Eg : "v1.2.3".
				'downloadUrl' => 'https://dev.azure.com/{organiztion}/{project}/_apis/git/repositories/{repositoryname}/items/items?path=/&$format=zip&download=true',
				'apiResponse' => $release,
			));
		}

		/**
		 * Get a branch by name.
		 *
		 * @param string $branchName
		 * @return null|Puc_v4p11_Vcs_Reference
		 */
		public function getBranch($branchName) {
			$reference = new Puc_v4p11_Vcs_Reference(array(
				'name'        => 'main',
				'downloadUrl' => 'https://dev.azure.com/{organiztion}/{project}/_apis/git/repositories/{repositoryname}/items/items?path=/&$format=zip&download=true',
				'apiResponse' => null,
			));

			$reference->updated = $this->getLatestCommitTime('main');
			return $reference;
		}

		/**
		 * Get the latest commit that changed the specified file.
		 *
		 * @param string $filename
		 * @param string $ref Reference name (e.g. branch or tag).
		 * @return StdClass|null
		 */
		public function getLatestCommit($filename, $ref = 'main') {
			/*
			 * https://docs.microsoft.com/en-us/rest/api/azure/devops/git/commits/get-commits?view=azure-devops-rest-6.0 
			 * Refer this for more info about commits and commit time
			 */
			$commits = $this->api('https://dev.azure.com/{organiztion}/_apis/git/repositories/{repositoryId}/commits?searchCriteria.itemVersion.version=main&api-version=6.0');
			return $commits->value[0];
		}

		/**
		 * Get the timestamp of the latest commit that changed the specified branch or tag.
		 *
		 * @param string $ref Reference name (e.g. branch or tag).
		 * @return string|null
		 */
		public function getLatestCommitTime($ref) {
					/*
			 * https://docs.microsoft.com/en-us/rest/api/azure/devops/git/commits/get-commits?view=azure-devops-rest-6.0 
			 * Refer this for more info about commits and commit time
			 */
			$commits = $this->api('https://dev.azure.com/{organization}/_apis/git/repositories/{repositoryId}/commits?searchCriteria.itemVersion.version=main&api-version=6.0');
			return $commits->value[0]->author->date;
		}

		/**
		 * Perform a GitHub API request.
		 *
		 * @param string $url
		 * @param array $queryParams
		 * @return mixed|WP_Error
		 */


		protected function api($url) {

			$options = array('timeout' => 10);
			$options['headers'] = array('Authorization' => $this->getAuthorizationHeader());

			if ( !empty($this->httpFilterName) ) {
				$options = apply_filters($this->httpFilterName, $options);
			}

			$response = wp_remote_get($url, $options);


			if ( is_wp_error($response) ) {
				do_action('puc_api_error', $response, null, $url, $this->slug);
				return $response;
			}

			$code = wp_remote_retrieve_response_code($response);

			$body = wp_remote_retrieve_body($response);

			if ( $code === 200 ) {
				$document = json_decode($body);
				return $document;
			}

			$error = new WP_Error(
				'puc-github-http-error',
				sprintf('GitHub API error. Base URL: "%s",  HTTP status code: %d.', $url, $code)
			);
			do_action('puc_api_error', $error, $response, $url, $this->slug);

			return $error;
		}


		/**
		 * Get the contents of a file from a specific branch or tag.
		 *
		 * @param string $path File name.
		 * @param string $ref
		 * @return null|string Either the contents of the file, or null if the file doesn't exist or there's an error.
		 */
		public function getRemoteFile($path, $ref = 'main') {
			/**
			 * https://docs.microsoft.com/en-us/rest/api/azure/devops/git/items/list?view=azure-devops-rest-6.0
			 * Url to refer
			 */
			$options = array('timeout' => 10);
			$options['headers'] = array('Authorization' => $this->getAuthorizationHeader());
			$url = 'https://dev.azure.com/{organiztion}/{project}/_apis/git/repositories/{repositoryId}/items?path=/{path_to_your_root__in_repo}/'.$path.'&download=true';
			$response = wp_remote_get($url, $options);
			$response_body = wp_remote_retrieve_body($response);
			return $response_body;
		}

		/**
		 * Get a specific tag.
		 *
		 * @param string $tagName
		 * @return void
		 */
		public function getTag($tagName) {
			//The current GitHub update checker doesn't use getTag, so I didn't bother to implement it.
			throw new LogicException('The ' . __METHOD__ . ' method is not implemented and should not be used.');
		}

		public function setAuthentication($credentials) {
			parent::setAuthentication($credentials);
			$this->accessToken = is_string($credentials) ? $credentials : null;

			//Optimization: Instead of filtering all HTTP requests, let's do it only when
			//WordPress is about to download an update.
			add_filter('upgrader_pre_download', array($this, 'addHttpRequestFilter'), 10, 1); //WP 3.7+
			$this->addHttpRequestFilter('');
		}

		/**
		 * Figure out which reference (i.e tag or branch) contains the latest version.
		 *
		 * @param string $configBranch Start looking in this branch.
		 * @return null|Puc_v4p11_Vcs_Reference
		 */
		public function chooseReference($configBranch) {
			$updateSource = $this->getLatestRelease();
			return $updateSource;
		}

		/**
		 * Enable updating via release assets.
		 *
		 * If the latest release contains no usable assets, the update checker
		 * will fall back to using the automatically generated ZIP archive.
		 *
		 * Private repositories will only work with WordPress 3.7 or later.
		 *
		 * @param string|null $fileNameRegex Optional. Use only those assets where the file name matches this regex.
		 */
		public function enableReleaseAssets($fileNameRegex = null) {
			$this->releaseAssetsEnabled = true;
			$this->assetFilterRegex = $fileNameRegex;
			$this->assetApiBaseUrl = sprintf(
				'//api.github.com/repos/%1$s/%2$s/releases/assets/',
				$this->userName,
				$this->repositoryName
			);
		}

		/**
		 * Does this asset match the file name regex?
		 *
		 * @param stdClass $releaseAsset
		 * @return bool
		 */
		protected function matchesAssetFilter($releaseAsset) {
			if ( $this->assetFilterRegex === null ) {
				//The default is to accept all assets.
				return true;
			}
			return isset($releaseAsset->name) && preg_match($this->assetFilterRegex, $releaseAsset->name);
		}
		public function addHttpRequestFilter($result) {
			if ( !$this->downloadFilterAdded && $this->isAuthenticationEnabled() ) {
				add_filter('http_request_args', array($this, 'setUpdateDownloadHeaders'), 10, 2);
				add_action('requests-requests.before_redirect', array($this, 'removeAuthHeaderFromRedirects'), 10, 4);
				$this->downloadFilterAdded = true;
			}
			return $result;
		}

		public function setUpdateDownloadHeaders($requestArgs, $url = '') {
			//Is WordPress trying to download one of our release assets?
			if ( $this->releaseAssetsEnabled && (strpos($url, $this->assetApiBaseUrl) !== false) ) {
				$requestArgs['headers']['Accept'] = 'application/octet-stream';
			}
			//Use Basic authentication, but only if the download is from our repository.
			/**
			 * I have modified this a bit
			 * U can make it more secure 
			 */
			if ( $this->isAuthenticationEnabled() &&  (strpos($url, 'https://dev.azure.com/') !== false )  ) {
				$requestArgs['headers']['Authorization'] = $this->getAuthorizationHeader();
			}
			return $requestArgs;
		}

		public function removeAuthHeaderFromRedirects(&$location, &$headers) {
			if ( isset($headers['Authorization']) ) {
				unset($headers['Authorization']);
			}
		}

		protected function getAuthorizationHeader() {
			return 'Basic '.base64_encode($this->userName.':'.$this->accessToken);
		}
	}

endif;