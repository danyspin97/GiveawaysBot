module Tyche
  module Entities
    class Winner < ActiveRecord::Base
      self.table_name = 'won'
      self.inheritance_column = 'unknown'
    end
  end
end
