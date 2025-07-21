# n8n-wordpress-interface
n8n Wordpress Interface is a WordPress plugin that integrates with [n8n](https://n8n.io) to automate sermon publishing workflows. It calls a webhook when audio files are uploaded and provides an approval dashboard for AI generated content, thus aiding is streamlining the publishing process for media teams.
## Features
- Triggers an n8n webhook when audio files are uploaded
- Sends location of audio file and different settings to n8n. Settings allow polyorphism within the workflow.
- Allows viewing and approval of AI generated content in flows from within WordPress
- Full integration with WordPress Admin styling and security
- Automatically handles security (via API key) and table configuration
## Getting started 
Download [n8n_workflow_plugin.php](n8n_workflow_plugin.php) and zip it. Then upload to the WordPress site and activate it. The plugin will automatically provision the necessary tables and generate an API key for authentication from within the n8n flow back to WordPress.  
<img width="1519" height="903" alt="image" src="https://github.com/user-attachments/assets/16ee36e0-c5ce-4770-b033-cece7c48d21e" />
<img width="2362" height="772" alt="image" src="https://github.com/user-attachments/assets/27bd0424-f775-4e7a-8a49-64fa0cec0d6a" />


## Usage
<img width="2600" height="1328" alt="image" src="https://github.com/user-attachments/assets/eeda94e3-17f0-46cb-a6ba-39d81b5a42c4" />
<img width="2448" height="1306" alt="image" src="https://github.com/user-attachments/assets/4f0005e0-e66b-4378-8576-a6c125e97b27" />



