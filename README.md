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

## Usage

