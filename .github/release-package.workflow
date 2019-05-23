workflow "Release a package" {
  resolves = ["Send message to #tyxla-test"]
  on = "release"
}

action "Send message to #tyxla-test" {
  uses = "Ilshidur/action-slack@f37693b4e0589604815219454efd5cb9b404fb85"
  secrets = ["SLACK_WEBHOOK"]
}
