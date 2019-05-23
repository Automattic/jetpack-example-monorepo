workflow "Release a package" {
  on = "release"
  resolves = ["Send message to Slack"]
}

action "Run only for tags" {
  uses = "actions/bin/filter@master"
  args = "tag"
}

action "Send message to Slack" {
  uses = "Ilshidur/action-slack@f37693b4e0589604815219454efd5cb9b404fb85"
  needs = ["Run only for tags"]
  secrets = ["SLACK_WEBHOOK"]
  args = "New Jetpack package released."
}
