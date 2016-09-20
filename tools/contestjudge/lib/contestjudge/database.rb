##
# Made easier access to a query's results.
class Database
  def initialize(wrapper)
    @wrapper = wrapper
  end

  def execute(query, &block)
    @wrapper.exec(query) do |result|
      process_result(result, &block)
    end
  end

  private

  def process_result(result, &block)
    result.each { |row| yield row } if block_given?
  end
end
