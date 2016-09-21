##
# Provide a placeholder for a database.
class FakeDB
  attr_reader :response

  def initialize
    @response = {
      'SELECT * FROM joined' => { 'chat_id' => '1334', 'language' => 'en' },
      'SELECT * FROM giveaway' => { 'id' => '0' },
      'SELECT * FROM "User"' => { 'language' => 'en' },
      'SELECT * FROM prize' => { 'name' => 'FakeAward' }
    }
  end

  def execute(query, &block)
    dict = @response.select { |key, _| query.start_with?(key) }
    yield dict.values.first if block_given?
  end
end
